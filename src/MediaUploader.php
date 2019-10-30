<?php

namespace Optix\Media;

use Exception;
use InvalidArgumentException;
use Optix\Media\Models\Media;
use Illuminate\Filesystem\FilesystemManager;
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
     * Create a new uploader instance.
     *
     * @param FilesystemManager $filesystemManager
     * @param string $model
     * @param string $disk
     * @return void
     */
    public function __construct(
        FilesystemManager $filesystemManager,
        string $model,
        string $disk
    ) {
        $this->filesystemManager = $filesystemManager;
        $this->setModel($model);
        $this->setDisk($disk);
    }

    /**
     * Initialise the uploader from a file instance.
     *
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

        throw new InvalidArgumentException();
    }

    /**
     * Initialise the uploader from a file path.
     *
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
     * Set the class name of the media model.
     *
     * @param string $model
     * @return self
     */
    public function setModel(string $model)
    {
        if (! is_a($model, Media::class, true)) {
            throw new InvalidArgumentException();
        }

        $this->model = $model;

        return $this;
    }

    /**
     * Set the disk used for file storage.
     *
     * @param string $disk
     * @return self
     *
     * @throws InvalidArgumentException
     */
    public function setDisk(string $disk)
    {
        $this->disk = $disk;

        return $this;
    }

    /**
     * Set the name of the file.
     *
     * @param string $fileName
     * @return self
     */
    public function setFileName(string $fileName)
    {
        $this->fileName = self::sanitiseFileName($fileName);

        return $this;
    }

    /**
     * Sanitise the given file name.
     *
     * @param string $fileName
     * @return string
     */
    public static function sanitiseFileName(string $fileName)
    {
        return str_replace(['#', '/', '\\', ' '], '-', $fileName);
    }

    /**
     * Set the name of the media item.
     *
     * @param string $name
     * @return self
     */
    public function setName(string $name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set any additional media item attributes.
     *
     * @param array $attributes
     * @return self
     */
    public function setAttributes(array $attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Set the file visibility.
     *
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
            throw new InvalidArgumentException();
        }

        $this->visibility = $visibility;

        return $this;
    }

    /**
     * Upload the file and create a media item.
     *
     * @return Media
     */
    public function upload()
    {
        try {
            $filesystem = $this->filesystemManager->disk($this->disk);
        } catch (Exception $exception) {
            throw new InvalidArgumentException();
        }

        $media = new $this->model;

        $media->name = $this->name;
        $media->file_name = $this->fileName;
        $media->disk = $this->disk;
        $media->mime_type = mime_content_type($this->filePath);
        $media->size = filesize($this->filePath);

        $media->fill($this->attributes);

        $media->save();

        // Save the file to the filesystem...
        $file = fopen($this->filePath, 'r');
        $filesystem->put($media->getPath(), $file);
        fclose($file);

        return $media;
    }
}
