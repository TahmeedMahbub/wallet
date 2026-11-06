<?php

namespace Tests\Feature\Isolation;

use App\Models\Concerns\BelongsToUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Tests\TestCase;

/**
 * A stand-in for any owned financial record, kept in a temporary table that
 * disappears with the connection. Real tables arrive with the domain phase and
 * reuse the same BelongsToUser trait and the same checks.
 */
class IsolationProbe extends Model
{
    use BelongsToUser;

    protected $table = 'isolation_probes';

    protected $guarded = [];

    public $timestamps = false;
}

class UserIsolationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // TEMPORARY statements never commit the test transaction; a plain DROP or
        // TRUNCATE would, which would leave test users behind in the real database.
        DB::statement('DROP TEMPORARY TABLE IF EXISTS isolation_probes');
        Schema::create('isolation_probes', function (Blueprint $table) {
            $table->temporary();
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('note');
        });
    }

    protected function tearDown(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS isolation_probes');

        parent::tearDown();
    }

    public function test_a_new_record_is_stamped_with_the_signed_in_user(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $probe = IsolationProbe::create(['note' => 'mine']);

        $this->assertSame($user->id, $probe->user_id);
    }

    public function test_a_user_never_sees_another_users_records(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all();

        $this->actingAs($alice);
        $alicesProbe = IsolationProbe::create(['note' => 'alice']);

        $this->actingAs($bob);
        IsolationProbe::create(['note' => 'bob']);

        $this->assertSame(['bob'], IsolationProbe::pluck('note')->all());
        $this->assertNull(IsolationProbe::find($alicesProbe->id));
        $this->assertSame(0, IsolationProbe::where('note', 'alice')->count());

        $this->actingAs($alice);
        $this->assertSame(['alice'], IsolationProbe::pluck('note')->all());
    }

    public function test_another_user_cannot_update_or_delete_a_record_through_a_query(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all();

        $this->actingAs($alice);
        $probe = IsolationProbe::create(['note' => 'alice']);

        $this->actingAs($bob);
        $this->assertSame(0, IsolationProbe::whereKey($probe->id)->update(['note' => 'hacked']));
        $this->assertSame(0, IsolationProbe::whereKey($probe->id)->delete());

        $this->actingAs($alice);
        $this->assertSame('alice', IsolationProbe::findOrFail($probe->id)->note);
    }

    public function test_nobody_signed_in_means_no_rows_are_visible(): void
    {
        $this->actingAs(User::factory()->create());
        IsolationProbe::create(['note' => 'private']);

        auth()->logout();

        $this->assertSame(0, IsolationProbe::count());
    }

    public function test_creating_a_record_without_a_signed_in_user_is_refused(): void
    {
        $this->expectException(LogicException::class);

        IsolationProbe::create(['note' => 'orphan']);
    }

    public function test_the_owner_of_a_record_cannot_be_changed(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all();
        $this->actingAs($alice);
        $probe = IsolationProbe::create(['note' => 'alice']);

        $this->expectException(LogicException::class);

        $probe->update(['user_id' => $bob->id]);
    }

    public function test_background_work_can_name_the_user_explicitly(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all();

        $this->actingAs($alice);
        IsolationProbe::create(['note' => 'alice']);
        $this->actingAs($bob);
        IsolationProbe::create(['note' => 'bob']);
        auth()->logout();

        $this->assertSame(['alice'], IsolationProbe::forUser($alice)->pluck('note')->all());
        $this->assertSame(['bob'], IsolationProbe::forUser($bob)->pluck('note')->all());
    }

    public function test_each_user_only_sees_their_own_name_on_home(): void
    {
        [$alice, $bob] = [User::factory()->create(['name' => 'Alice Example']), User::factory()->create(['name' => 'Bob Example'])];

        $this->actingAs($alice)->get('/')->assertSee('Alice Example')->assertDontSee('Bob Example');
        $this->actingAs($bob)->get('/')->assertSee('Bob Example')->assertDontSee('Alice Example');
    }

    public function test_one_users_session_cannot_be_used_as_another_user(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create()->all();

        $this->actingAs($alice)->get('/')->assertOk();
        $this->assertAuthenticatedAs($alice);
        $this->assertFalse(auth()->user()->is($bob));
    }
}
