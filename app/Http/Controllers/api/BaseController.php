<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadFileRequest;
use App\Http\Requests\UploadingFileListRequest;
use Illuminate\Support\Facades\File;
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



    private function dirExists(string $path)
    {
        $params = [
            'path' => $path,
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

    private function createDir($path)
    {
        $params = [
            'path' =>  $path,
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->send('PUT', self::YA_URL, [
            'query' => $params,
            'body'  => '', // explicitly no payload
        ]);
        
        if($response->status() == 201) {
            Log::info('Директория '. $path .' создана на Ya.Disk');
            return true;
        } elseif($response->status() == 409) {
            Log::info('Директория '. $path .' не создана на Ya.Disk');
            Log::warning($response->body());
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

        $projects = [];

        $peleton = config('projectGroup.project_groups.peleton');
        $rndKrd = config('projectGroup.project_groups.krd_rnd');

        $projects = array_merge($peleton, $rndKrd);

        if(!in_array($domain, $projects)) {
            return response()->json([
                'message' => 'Сайта '.$domain.' нет в проектах',
            ]);
        }

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
            
            $dirName = explode('/', $dir)[1];

            $locations = config('projectGroup.project_groups');
            $currentLocation = '';

            foreach ($locations as $location => $domains) {
                if(in_array($dirName, $domains)) {
                    $currentLocation = $location;
                    if (!$this->dirExists('/заявки' . '/' . $currentLocation)) {
                        $this->createDir('/заявки'. '/' . $currentLocation);
                    }
                }
            }

            if($currentLocation == '') {
                Log::alert('Нет локации для ' . $dirName);
                continue;
            }

            if (!$this->dirExists('/заявки' . '/' . $currentLocation . '/' . $dirName)) {
                $this->createDir('/заявки' . '/' . $currentLocation . '/' . $dirName);
            }

            foreach ($allFiles as $filePath) {
                $this->uploadFileToYandexDisk('/заявки' . '/' . $currentLocation . '/' . $dirName, Storage::path($filePath));
            }
        }

    }

    public function uploadFileToYandexDisk(string $path, string $localFilePath)
    {
        // Получаем URL для загрузки файла
        $params = [
            'path' => $path . '/' . basename($localFilePath),
            'overwrite' => 'true',
        ];

        $response = Http::withHeaders([
            'Authorization' => $this->apiKey,
        ])->get(self::YA_URL . '/upload', $params);

        if ($response->successful()) {
            $uploadUrl = $response->json()['href'];

            // Загружаем файл на полученный URL
            $fileContents = File::get($localFilePath);
            // $uploadResponse = Http::put($uploadUrl, $fileContents);
            // Запрос с отключенной проверкой SSL-сертификата
            $uploadResponse = Http::withoutVerifying()->put($uploadUrl, $fileContents);

            // задержка что бы отправка не падала за превышение количества запрсов
            sleep(3);

            if ($uploadResponse->successful()) {
                Log::info('Файл '.$localFilePath.' обновлен или загружен на Ya.Disk');
                Storage::directoryExists('downloaded') || Storage::makeDirectory('downloaded');
                File::move($localFilePath, Storage::path('downloaded/' . basename($localFilePath)));
                return response()->json(['message' => 'File uploaded successfully']);
            } else {
                Log::info('Загрузка не удалась ' . $localFilePath);
                Log::warning($response->body());
                return response()->json(['message' => 'File upload failed'], 500);
            }
        } else {
            Log::info('Загрузка не удалась ' . $localFilePath);
            Log::warning($response->body());
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
