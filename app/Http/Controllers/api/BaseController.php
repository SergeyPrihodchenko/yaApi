<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Http\Requests\UploadingFileListRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BaseController extends Controller
{
    const YA_URL = 'https://cloud-api.yandex.net/v1/disk/resources';
    protected $apiKey;

    public function __construct()
    {
        $this->apiKey = 'OAuth ' . env('YADISK_API_KEY');
    }

    public function getAllList()
    {
        $files = [];
        $page = 0;
        $limit = 1000; // Максимальное количество записей на одну страницу

        do {
            $params = [
                'path' => 'Логи сайтов',
                'limit' => $limit,
                'offset' => $page * $limit,
            ];

            $response = Http::withHeaders([
                'Authorization' => $this->apiKey,
            ])
            ->acceptJson() // Устанавливаем заголовок Accept: application/json
            ->get(self::YA_URL, $params);

            if ($response->successful()) {
                $data = $response->json(); // Преобразуем ответ в массив
                $files = array_merge($files, $data['_embedded']['items']); // Объединяем полученные элементы
                $page++;
            } else {
                break; // Ошибка при запросе
            }
        } while (!empty($data['_embedded']['items']));
        $result = response()->json($files);

        return $result; // Возвращаем полный список файлов
    }



    private function dirExists($dirPath)
    {
        $params = [
            'path' => $dirPath,
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->get(self::YA_URL, $params);

        if($response->status() == 200) {
            return true;
        } else {
            return false;
        }
    }

    private function createDir($dirPath)
    {
        $params = [
            'path' => $dirPath,
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->send('PUT', self::YA_URL, [
            'query' => $params,
            'body'  => '', // explicitly no payload
        ]);
        
        if($response->status() == 201) {
            return true;
        } elseif($response->status() == 409) {
            return true;
        } else {
            return false;
        }
    }

    public function uploadFileInLical(UploadFileRequest $request)
    {
        $ip = $request->ip();

        if (config('ipList.allowed_ips') && !in_array($ip, config('ipList.allowed_ips'))) {
            return response()->json(['message' => 'Unauthorized IP address'], 403);
        }

        $validated = $request->validated();

        $file = $validated['file'];

        $fileName = $file->getClientOriginalName();

        $domain = $this->getDomainInFileName($fileName);

        $uploadedLogs = 'uploaded_logs';

        Storage::directoryExists($uploadedLogs) || Storage::makeDirectory($uploadedLogs);

        Storage::directoryExists($uploadedLogs . '/' . $domain) || Storage::makeDirectory($uploadedLogs . '/' . $domain);

        $allFiles = Storage::allFiles($uploadedLogs . '/' . $domain);

        if(in_array($uploadedLogs . '/' . $domain . '/' . $fileName, $allFiles)) {
            return response()->json([
                'message' => 'File exist'
            ]);
        }

        Log::alert('Файл добавлен ' . $fileName);

        Storage::putFileAs('uploaded_logs/'.$domain, $file, $fileName);

        return response()->json([
            'message' => 'File uploaded successfully',
            'file_name' => $fileName,
        ]);
    }

    public function uploadFile()
    {
        $uploadedLogs = 'uploaded_logs';

        $allDirsByDomains = Storage::directories($uploadedLogs);

        foreach ($allDirsByDomains as $dir) {

            $allFiles = Storage::allFiles($dir);

            if (!$this->dirExists($dir)) {
                $this->createDir($dir);
            }

            foreach ($allFiles as $filePath) {
                $this->uploadFileToYandexDisk($dir, Storage::path($filePath));
            }
        }

    }

    public function uploadFileToYandexDisk($dir, $localFilePath)
    {

        // Получаем URL для загрузки файла
        $params = [
            'path' => 'test' . '/' . $dir . '/' . basename($localFilePath),
            'overwrite' => 'true',
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->get(self::YA_URL . '/upload', $params);

        if ($response->successful()) {
            $uploadUrl = $response->json()['href'];

            // Загружаем файл на полученный URL
            $fileContents = Storage::get($localFilePath);
            // $uploadResponse = Http::put($uploadUrl, $fileContents);
            // Запрос с отключенной проверкой SSL-сертификата
            $uploadResponse = Http::withoutVerifying()->put($uploadUrl, $fileContents);

            if ($uploadResponse->successful()) {
                return response()->json(['message' => 'File uploaded successfully']);
            } else {
                return response()->json(['message' => 'File upload failed'], 500);
            }
        } else {
            return response()->json(['message' => 'Failed to get upload URL'], 500);
        }
    }

    public function uploadListFiles(UploadingFileListRequest $request)
    {
        $ip = $request->ip();

        if (config('ipList.allowed_ips') && !in_array($ip, config('ipList.allowed_ips'))) {
            return response()->json(['message' => 'Unauthorized IP address'], 403);
        }

        $validated = $request->validated();

        $fileList = $validated['file_list'];

        $fileName = str_replace(".", '_', $ip);

        Storage::directoryExists('file_lists') || Storage::makeDirectory('file_lists');

        Storage::put('file_lists/' . $fileName . '.txt', implode(PHP_EOL, $fileList));

        return response()->json([
            'message' => 'File list received successfully',
            'file_list' => $validated['file_list'],
        ]);
    }

    public function getDomainInFileName(string $fileName): string
    {
        $parts = explode('-', $fileName);

        $domainParts = array_slice($parts, 3, -1); 
        $domain = implode('-', $domainParts);
        return $domain;
    }
}
