<?php

namespace Optix\Media\Tests;

use Mockery;
use Optix\Media\Models\Media;
use Optix\Media\MediaUploader;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploaderTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_upload_media_from_an_uploaded_file_instance()
    {
        $defaultModel = Media::class;
        $defaultDisk = 'public';

        // Mock filesystem...
        $fakeFilesystem = Storage::fake($defaultDisk);

        // Mock filesystem manager...
        $fakeFilesystemManager = Mockery::mock(FilesystemManager::class);
        $fakeFilesystemManager->shouldReceive('disk')
            ->with($defaultDisk)->once()
            ->andReturn($fakeFilesystem);

        $mediaUploader = new MediaUploader(
            $fakeFilesystemManager,
            $defaultModel,
            $defaultDisk
        );

        $file = new UploadedFile(
            $filePath = __DIR__."/files/document.txt",
            $fileName = 'original-file-name.txt'
        );

        $media = $mediaUploader->fromFile($file)->upload();

        $this->assertInstanceOf($defaultModel, $media);

        $this->assertEquals('original-file-name', $media->name);
        $this->assertEquals($fileName, $media->file_name);
        $this->assertEquals($defaultDisk, $media->disk);
        $this->assertEquals('text/plain', $media->mime_type);
        $this->assertEquals(filesize($filePath), $media->size);

        $this->assertTrue($fakeFilesystem->exists($media->getPath()));
    }
}
