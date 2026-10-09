<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth.php';

function runtime_cache_location(): string
{
    foreach (['HF_HOME', 'HF_HUB_CACHE', 'HUGGINGFACE_HUB_CACHE', 'TRANSFORMERS_CACHE', 'SENTENCE_TRANSFORMERS_HOME', 'HF_DATASETS_CACHE'] as $name) {
        $value = getenv($name);
        if ($value !== false && trim($value) !== '') {
            return trim($value);
        }
    }

    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'hf_cache';
}

$pythonPath = null;
$pythonRuns = false;
try {
    $pythonPath = python_executable();
    $outputPath = tempnam(sys_get_temp_dir(), 'curriculum-runtime-check-');
    if ($outputPath === false) {
        throw new RuntimeException('Unable to prepare the runtime check.');
    }
    try {
        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $descriptors = [
            0 => ['file', $nullDevice, 'r'],
            1 => ['file', $outputPath, 'w'],
            2 => ['redirect', 1],
        ];
        $process = @proc_open(
            [$pythonPath, '-c', 'import sys; print(sys.version.split()[0])'],
            $descriptors,
            $pipes
        );
        if (is_resource($process)) {
            $deadline = microtime(true) + 10;
            $pythonExitCode = null;
            do {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $pythonExitCode = (int)$status['exitcode'];
                    break;
                }
                if (microtime(true) >= $deadline) {
                    terminate_python_process($process, (int)$status['pid'], false);
                    break;
                }
                usleep(50000);
            } while (true);
            proc_close($process);
            $output = file_get_contents($outputPath);
            $pythonRuns = $pythonExitCode === 0 && is_string($output) && trim($output) !== '';
        }
    } finally {
        if (is_file($outputPath)) {
            @unlink($outputPath);
        }
    }
} catch (Throwable $error) {
    error_log('Runtime diagnostic could not execute the configured Python interpreter.');
}

$knowledgeBase = curriculum_kb_dir();
$databasePath = __DIR__ . '/../curriculum_matching.db';
$databaseWritable = is_file($databasePath)
    ? is_writable($databasePath)
    : is_dir(dirname($databasePath)) && is_writable(dirname($databasePath));
$knowledgeBaseFiles = [
    'course_dataset' => is_file($knowledgeBase . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'curriculum_dataset_with_ids.csv'),
    'industry_skills' => is_file($knowledgeBase . DIRECTORY_SEPARATOR . '03_industry_skills_data.md'),
];

header('Content-Type: text/plain; charset=utf-8');
echo 'Python path: ' . ($pythonPath ?? 'unavailable') . PHP_EOL;
echo 'Python runs: ' . ($pythonRuns ? 'yes' : 'no') . PHP_EOL;
echo 'KB_DIR: ' . $knowledgeBase . PHP_EOL;
echo 'KB course dataset exists: ' . ($knowledgeBaseFiles['course_dataset'] ? 'yes' : 'no') . PHP_EOL;
echo 'KB industry skills file exists: ' . ($knowledgeBaseFiles['industry_skills'] ? 'yes' : 'no') . PHP_EOL;
echo 'Effective HF cache location: ' . runtime_cache_location() . PHP_EOL;
echo 'Database writable: ' . ($databaseWritable ? 'yes' : 'no') . PHP_EOL;
