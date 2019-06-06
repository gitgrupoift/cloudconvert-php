<?php


namespace CloudConvert\Tests\Integration;


use CloudConvert\Models\ConvertTask;
use CloudConvert\Models\ExportUrlTask;
use CloudConvert\Models\ImportUploadTask;
use CloudConvert\Models\ImportUrlTask;
use CloudConvert\Models\Job;
use CloudConvert\Models\Task;

class JobTest extends TestCase
{

    public function testCreateJob()
    {

        $job = (new Job())
            ->addTask(
                (new Task('import/url', 'import-it'))
                    ->set('url', 'http://invalid.url')
                    ->set('filename', 'test.file')
            )
            ->addTask(
                (new Task('convert', 'convert-it'))
                    ->set('input', ['import-it'])
                    ->set('output_format', 'pdf')
            );

        $this->cloudConvert->jobs()->create($job);

        $this->assertNotNull($job->getId());
        $this->assertNotNull($job->getCreatedAt());
        $this->assertCount(2, $job->getTasks());

        $task1 = $job->getTasks()[0];
        $task2 = $job->getTasks()[1];

        $this->assertEquals('import/url', $task1->getOperation());
        $this->assertEquals('import-it', $task1->getName());
        $this->assertEquals([
            'operation' => 'import/url',
            'url'       => 'http://invalid.url',
            'filename'  => 'test.file'
        ], (array)$task1->getPayload());

        $this->assertEquals('convert', $task2->getOperation());
        $this->assertEquals('convert-it', $task2->getName());
        $this->assertEquals([
            'operation'     => 'convert',
            'input'         => ['import-it'],
            'output_format' => 'pdf',
        ], (array)$task2->getPayload());


    }


    public function testUploadAndDownloadFiles()
    {

        $job = (new Job())
            ->addTask(
                new Task('import/upload', 'import-it')
            )
            ->addTask(
                (new Task('export/url', 'export-it'))
                    ->set('input', ['import-it'])
            );

        $this->cloudConvert->jobs()->create($job);

        $uploadTask = $job->getTasks()->name('import-it')[0];

        $this->cloudConvert->tasks()->upload($uploadTask, fopen(__DIR__ . '/files/input.pdf', 'r'));

        while ($job->getStatus() !== Job::STATUS_FINISHED) {
            sleep(1);
            $this->cloudConvert->jobs()->refresh($job);
        }

        $exportTask = $job->getTasks()->status(Task::STATUS_FINISHED)->name('export-it')[0];

        $this->assertNotNull($exportTask->getResult());

        $file = $exportTask->getResult()->files[0];

        $this->assertNotEmpty($file->url);

        $source = $this->cloudConvert->getHttpTransport()->download($file->url)->detach();

        $dest = tmpfile();
        $destPath = stream_get_meta_data($dest)['uri'];

        stream_copy_to_stream($source, $dest);


        $this->assertEquals(filesize($destPath), 172570);


    }


}
