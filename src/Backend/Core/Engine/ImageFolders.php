<?php

namespace Backend\Core\Engine;

/**
 * This class represents a image-folders-object
 *
 * @author Jeroen Desloovere <jeroen@siesqo.be>
 */
class ImageFolders
{
    /**
     * @var ImageFolder[]
     */
    private $imageFolders;

    /**
     * Construct
     *
     * @param string $folder
     * @param array $folders
     */
    public function __construct($folder, array $folders)
    {
        // define image folders
        foreach ($folders as $folder) {
            $this->imageFolders[] = new ImageFolder($folder, $folders);
        }
    }

    /**
     * Get all image folders
     *
     * @return ImageFolder[]
     */
    public function getAll()
    {
        return $this->imageFolders;
    }
}
