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

    /** @var Filesystem */
    protected $filesystem;

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

        // Todo: Dedicated exception class...
        throw new InvalidArgumentException(
            vsprintf("The file parameter must be an instance of \"%s\" or \"%s\".", [
                UploadedFile::class,
                File::class,
            ])
        );
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
        try {
            $this->filesystem = $this
                ->filesystemManager
                ->disk($disk);
        } catch (Exception $exception) {
            // Todo: Dedicated exception class...
            throw new InvalidArgumentException(
                "Disk \"{$disk}\" cannot be resolved."
            );
        }

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
        if (! in_array($visibility, $visibilities = [
            self::VISIBILITY_PUBLIC,
            self::VISIBILITY_PRIVATE,
        ])) {
            // Todo: Dedicated exception class...
            throw new InvalidArgumentException(
                vsprintf("Visibility \"{$visibility}\" is not one of the accepted values: \"%s\".", [
                    implode('", "', $visibilities)
                ])
            );
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
        $media = $this->makeModel();

        $media->name = $this->name;
        $media->file_name = $this->fileName;
        $media->disk = $this->disk;
        $media->mime_type = mime_content_type($this->filePath);
        $media->size = filesize($this->filePath);

        $media->fill($this->attributes);

        $media->save();

        // Save the file to the filesystem...
        $file = fopen($this->filePath, 'r');
        $this->filesystem->put($media->getPath(), $file);
        fclose($file);

        return $media;
    }

    /**
     * Create a new media model instance.
     *
     * @return Media
     */
    protected function makeModel()
    {
        return new $this->model;
    }
}
