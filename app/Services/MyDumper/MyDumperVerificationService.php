<?php

namespace App\Services\MyDumper;

use App\Enums\MyDumper\MyDumperExportStage;
use App\Enums\MyDumper\MyDumperExportStatus;
use App\Models\MyDumperExport;
use App\Models\MyDumperExportFile;
use App\Services\BaseService;
use Illuminate\Support\Facades\File;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class MyDumperVerificationService extends BaseService
{
    public function __construct(
        private readonly MyDumperLogService $logService,
    ) {}

    public function verify(MyDumperExport $export): void
    {
        $outputPath = $export->output_path;

        if ($outputPath === null || ! File::isDirectory($outputPath)) {
            throw \App\Exceptions\MyDumper\MyDumperException::verificationFailed('Folder export tidak ditemukan.');
        }

        $files = $this->indexFiles($outputPath);

        if ($files === []) {
            throw \App\Exceptions\MyDumper\MyDumperException::verificationFailed('Tidak ada file hasil export.');
        }

        $hasMetadata = collect($files)->contains(fn (array $file) => str_contains(strtolower($file['relative_path']), 'metadata'));

        if (! $hasMetadata && ($export->options_snapshot['build_metadata'] ?? true)) {
            throw \App\Exceptions\MyDumper\MyDumperException::verificationFailed('File metadata tidak ditemukan.');
        }

        $emptyFiles = collect($files)->filter(fn (array $file) => $file['size_bytes'] === 0)->pluck('relative_path')->all();

        if ($emptyFiles !== []) {
            throw \App\Exceptions\MyDumper\MyDumperException::verificationFailed(
                'File kosong ditemukan: '.implode(', ', array_slice($emptyFiles, 0, 5))
            );
        }

        $totalSize = collect($files)->sum('size_bytes');
        $checksum = md5($outputPath.'|'.$totalSize.'|'.count($files));

        MyDumperExportFile::query()->where('export_id', $export->id)->delete();

        foreach ($files as $file) {
            MyDumperExportFile::create([
                'export_id' => $export->id,
                'relative_path' => $file['relative_path'],
                'size_bytes' => $file['size_bytes'],
                'table_name' => $file['table_name'],
                'checksum' => md5_file($file['absolute_path']),
            ]);
        }

        $export->update([
            'file_count' => count($files),
            'total_size' => $totalSize,
            'checksum' => $checksum,
            'verification_status' => 'passed',
            'verification_message' => 'Verifikasi berhasil.',
            'metadata_path' => collect($files)
                ->first(fn (array $file) => str_contains(strtolower($file['relative_path']), 'metadata'))['absolute_path'] ?? null,
        ]);

        $this->logService->append($export, 'Verification passed.', 'info', 'system');
    }

    /**
     * @return array<int, array{relative_path: string, absolute_path: string, size_bytes: int, table_name: ?string}>
     */
    public function indexFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $absolutePath = $file->getPathname();
            $relativePath = ltrim(str_replace($directory, '', $absolutePath), DIRECTORY_SEPARATOR.'/\\');
            $tableName = $this->guessTableName($relativePath);

            $files[] = [
                'relative_path' => str_replace('\\', '/', $relativePath),
                'absolute_path' => $absolutePath,
                'size_bytes' => $file->getSize(),
                'table_name' => $tableName,
            ];
        }

        return $files;
    }

    private function guessTableName(string $relativePath): ?string
    {
        $basename = basename($relativePath);

        if (preg_match('/^([a-zA-Z0-9_]+)[.-]/', $basename, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
