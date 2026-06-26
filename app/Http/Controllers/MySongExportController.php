<?php

namespace App\Http\Controllers;

use App\Exports\MySongsExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MySongExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        // CSVの中身づくりはExportクラスに任せ、Controllerはダウンロードを返す
        $export = new MySongsExport(auth()->user());

        return response()->streamDownload(
            fn () => $export->stream(),
            $export->filename(),
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }
}
