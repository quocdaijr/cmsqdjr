<?php

namespace Modules\File\Services;

use Auth;
use File;
use Illuminate\Contracts\Filesystem\Factory;
use Illuminate\Http\UploadedFile;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Modules\Core\Constants\CoreConstant;
use Modules\File\Constants\FileConstant;
use Modules\File\Jobs\ResizeImage;
use Modules\File\Repositories\Interfaces\FileRepositoryInterface;
use Str;

class FileService
{
    private $disk;

    public function __construct(
        protected Factory                 $filesystem,
        protected FileRepositoryInterface $fileRepository
    )
    {
        $this->disk = $this->filesystem->disk($this->getConfigFilesystem());
    }

    public function store(UploadedFile $file)
    {
        $fileExtension = !empty($file->getClientOriginalExtension())
            ? $file->getClientOriginalExtension()
            : $file->guessExtension();

        $filename = !empty($file->getClientOriginalExtension())
            ? $file->getClientOriginalName()
            : rtrim($file->getClientOriginalName(), '.') . '.' . $file->guessExtension();

        $basename = File::name($filename);

        $path = $this->getRawFolderName() . DIRECTORY_SEPARATOR . date('Y') . DIRECTORY_SEPARATOR
            . date('m') . DIRECTORY_SEPARATOR . date('d') . DIRECTORY_SEPARATOR
            . Str::slug($basename) . '-' . time() . '.' . $fileExtension;

        $stream = fopen($file->getRealPath(), 'r+');
        $saveFile = $this->disk->writeStream($path, $stream, [
            'visibility' => 'public',
            'mimetype' => $file->getMimeType(),
        ]);

        if ($saveFile) {
            $data = [
                'name' => $filename,
                'title' => $basename,
                'status' => CoreConstant::STATUS_ACTIVE,
                'raw_path' => $this->disk->getDriver()->getAdapter()->getPathPrefix() . $path,
                'path' => $path,
                'where' => $this->getConfigFilesystem(),
                'mimetype' => $file->getMimeType(),
                'type' => $this->getTypeByMimeType($file->getMimeType()),
                'size' => $file->getSize(),
                'mtime' => $file->getMTime(),
                'created_by' => Auth::id()
            ];
            $dbFile = $this->fileRepository->create($data);

            if ($data['type'] == FileConstant::FILE_TYPE_IMAGE) {
                dispatch(new ResizeImage($data['path']));
            }
        }
        return $dbFile ?? null;
    }

    public function update(int $id, array $data)
    {
        if (!empty($oldFile = $this->fileRepository->find($id))) {
            $this->fileRepository->update($id, $data);
            if ($oldFile->type == FileConstant::FILE_TYPE_IMAGE) {
                dispatch(new ResizeImage($oldFile->path));
            }
            return true;
        }
        return false;
    }


    public function resize($path)
    {
        try {
            if (!empty($path) && $this->disk->exists($path)) {
                // Create ImageManager instance with GD driver
                $manager = new ImageManager(new Driver());

                foreach ((array)$this->getSizes() as $name => $size) {
                    list($width, $height) = $size;
                    $resizePath = $this->getResizeFolderName() . DIRECTORY_SEPARATOR . $name
                        . DIRECTORY_SEPARATOR . ltrim($path, $this->getRawFolderName() . DIRECTORY_SEPARATOR);
                    if (!$this->disk->exists($resizePath)) {
                        list($rawWidth, $rawHeight) = getimagesize($this->disk->url($path));
                        $resizeWidth = ($rawWidth < $width) ? $rawWidth : $width;
                        $resizeHeight = ($rawHeight < $height) ? $rawHeight : $height;

                        // Read image and scale maintaining aspect ratio
                        $image = $manager->read($this->disk->url($path));
                        if ($resizeWidth > $resizeHeight) {
                            $image->scale(width: $resizeWidth);
                        } else {
                            $image->scale(height: $resizeHeight);
                        }

                        $this->disk->put($resizePath, $image->encode(), [
                            'visibility' => 'public',
                            'mimetype' => $this->disk->mimeType($path),
                        ]);
                    }
                }
                return true;
            }
            return false;
        } catch (\Exception $e) {
            print_r($e);
            return false;
        }
    }

    public function delete($id)
    {
        $dbFile = $this->fileRepository->find($id);
        if (!empty($dbFile->path)) {
            if ($this->disk->exists($dbFile->path)) {
                $this->disk->delete($dbFile->path);
            }
            foreach ((array)$this->getSizes() as $name => $size) {
                $resizePath = $this->getResizeFolderName() . DIRECTORY_SEPARATOR . $name
                    . DIRECTORY_SEPARATOR . ltrim($dbFile->path, $this->getRawFolderName() . DIRECTORY_SEPARATOR);
                if ($this->disk->exists($resizePath)) {
                    $this->disk->delete($resizePath);
                }
            }
            if ($this->fileRepository->update($id, ['status' => CoreConstant::STATUS_DELETED]))
                return true;
        }
        return false;
    }

    private function getConfigFilesystem()
    {
        return config('filesystems.default');
    }

    private function getRawFolderName()
    {
        return config(CoreConstant::MODULE_NAME . '.file.config.raw_folder') ?? 'r';
    }

    private function getResizeFolderName()
    {
        return config(CoreConstant::MODULE_NAME . '.file.config.resize_folder') ?? 'i';
    }

    private function getSizes()
    {
        return config(CoreConstant::MODULE_NAME . '.file.config.sizes');
    }


    private function getTypeByMimeType($mimetype)
    {
        $mimetypes = config(CoreConstant::MODULE_NAME . '.file.config.mimetypes');
        $type = 'other';
        foreach ($mimetypes as $key => $value) {
            if (in_array($mimetype, $value)) {
                $type = $key;
                break;
            }
        }
        return $type;
    }
}
