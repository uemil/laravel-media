<?php

namespace Optix\Media;

use Exception;
use InvalidArgumentException;
use Optix\Media\Models\Media;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaUploader
{
    /** @var string */
    protected $model;

    /** @var string */
    protected $disk;

    /** @var string */
    protected $filePath;

    /** @var string */
    protected $fileName;

    /** @var string */
    protected $name;

    /** @var array */
    protected $attributes = [];

    /** @var string */
    protected $visibility;

    /** @var FilesystemManager */
    protected $filesystemManager;

    /** @var string */
    const VISIBILITY_PUBLIC = 'public';

    /** @var string */
    const VISIBILITY_PRIVATE = 'private';

    /**
     * @param FilesystemManager $filesystemManager
     * @param array $config
     * @return void
     */
    public function __construct(FilesystemManager $filesystemManager, array $config)
    {
        $this->filesystemManager = $filesystemManager;
        $this->setModel($config['model']);
        $this->setDisk($config['disk']);
    }

    /**
     * @param UploadedFile|File $file
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public function fromFile($file)
    {
        if ($file instanceof UploadedFile) {
            $this->filePath = $file->getRealPath();
            $this->setFileName($fileName = $file->getClientOriginalName());
            $this->setName(pathinfo($fileName, PATHINFO_FILENAME));

            return $this;
        }

        if ($file instanceof File) {
            return $this->fromPath($file->getRealPath());
        }

        // Todo: Dedicated exception class...
        throw new InvalidArgumentException();
    }

    /**
     * @param string $path
     * @return self
     */
    public function fromPath(string $path)
    {
        $this->filePath = $path;
        $this->setFileName(pathinfo($path, PATHINFO_BASENAME));
        $this->setName(pathinfo($path, PATHINFO_FILENAME));

        return $this;
    }

    /**
     * @param string $model
     * @return self
     */
    public function setModel(string $model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * @param string $disk
     * @return self
     */
    public function setDisk(string $disk)
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * @param string $fileName
     * @return self
     */
    public function setFileName(string $fileName)
    {
        $this->fileName = self::sanitiseFileName($fileName);

        return $this;
    }

    /**
     * @param string $fileName
     * @return mixed
     */
    public static function sanitiseFileName(string $fileName)
    {
        return str_replace(['#', '/', '\\', ' '], '-', $fileName);
    }

    /**
     * @param string $name
     * @return self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @param array $attributes
     * @return self
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * @param string $visibility
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public function setVisibility(string $visibility)
    {
        if (! in_array($visibility, [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_PRIVATE,
        ])) {
            // Todo: Dedicated exception class...
            throw new InvalidArgumentException();
        }

        $this->visibility = $visibility;

        return $this;
    }

    /**
     * @return Media
     *
     * @throws Exception
     */
    public function upload()
    {
        $filesystem = $this->resolveFilesystem();

        $media = $this->makeModel();

        $media->name = $this->name;
        $media->file_name = $this->fileName;
        $media->disk = $this->disk;
        $media->mime_type = mime_content_type($this->filePath);
        $media->size = filesize($this->filePath);

        $media->fill($this->attributes);

        $media->save();

        $file = fopen($this->filePath, 'r');
        $filesystem->put($media->getPath(), $file);
        fclose($file);

        return $media;
    }

    /**
     * @return Filesystem
     *
     * @throws Exception
     */
    protected function resolveFilesystem()
    {
        try {
            return $this->filesystemManager->disk($this->disk);
        } catch (Exception $exception) {
            throw new Exception();
        }
    }

    /**
     * @return Media
     */
    protected function makeModel()
    {
        return new $this->model;
    }
}
