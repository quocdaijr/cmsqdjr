<?php

namespace Modules\File\Http\Controllers\Api;

use File;
use Illuminate\Contracts\Filesystem\Factory;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Modules\File\Services\FileService;

class ResizeController
{
    private $disk;

    public function __construct(
        public FileService $fileService,
        public Factory     $filesystem
    )
    {
        $this->disk = $this->filesystem->disk(config('filesystems.default'));
    }

    public function fly($size, $imagePath)
    {
        if (!$this->disk->exists($imagePath)) {
            abort(404);
        }

        print_r(getimagesize($this->disk->path($imagePath)));exit();

        $resizedPath = 'i/' . $size . '/' . $imagePath;

        // Create ImageManager instance
        $manager = new ImageManager(new Driver());

//        if ($this->disk->exists($resizedPath)) {
//            $image = $manager->read($this->disk->path($resizedPath));
//            return response($image->encode(), 200)->header('Content-Type', $this->disk->mimeType($resizedPath));
//        }

        $savedDir = dirname($resizedPath);
        if (!$this->disk->exists($savedDir)) {
            $this->disk->makeDirectory($savedDir);
        }

        list($width, $height) = explode('x', strtolower($size));

        // Read and resize image with new API
        $image = $manager->read($this->disk->path($imagePath));
        $image->scale(width: (int)$width, height: (int)$height);

        $this->disk->put($resizedPath, $image->encode(), [
            'visibility' => 'public',
            'mimetype' => $this->disk->mimeType($imagePath),
        ]);

        // Return image response
        return response($image->encode(), 200)->header('Content-Type', $this->disk->mimeType($imagePath));
    }
}
