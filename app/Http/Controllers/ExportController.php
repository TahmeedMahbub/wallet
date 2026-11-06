<?php

namespace App\Http\Controllers;

use App\Services\Backup\UserDataExporter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads of the signed-in user's own data. Nothing is stored on the server.
 */
class ExportController extends Controller
{
    public function json(Request $request, UserDataExporter $exporter): StreamedResponse
    {
        $name = 'wallet-'.now($request->user()->timezone)->format('Y-m-d').'.json';
        $content = $exporter->json($request->user());

        return response()->streamDownload(function () use ($content): void {
            echo $content;
        }, $name, ['Content-Type' => 'application/json', 'Cache-Control' => 'no-store']);
    }

    public function csv(Request $request, UserDataExporter $exporter): StreamedResponse
    {
        $path = $exporter->csvZip($request->user());
        $name = 'wallet-'.now($request->user()->timezone)->format('Y-m-d').'-csv.zip';

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $name, ['Content-Type' => 'application/zip', 'Cache-Control' => 'no-store']);
    }
}
