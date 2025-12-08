<?php

namespace App\Http\Controllers;

use App\Http\Requests\UploadingFileListRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BaseController extends Controller
{
    public function uploadListFiles(UploadingFileListRequest $request)
    {
        $ip = $request->ip();

        // if (config('ipList.allowed_ips') && !in_array($ip, config('ipList.allowed_ips'))) {
        //     return response()->json(['message' => 'Unauthorized IP address'], 403);
        // }

        if('212.193.30.100' !== $ip) {
            return response()->json(['message' => 'Unauthorized IP address'], 403);
        }

        $validated = $request->validated();

        $fileList = $validated['file_list'];

        Storage::put('file_list.txt', implode(PHP_EOL, $fileList));

        return response()->json([
            'message' => 'File list received successfully',
            'file_list' => $validated['file_list'],
        ]);
    }
}
