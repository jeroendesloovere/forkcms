<?php

namespace Backend\Core\Engine;

/**
 * This class represents a image-folder-object
 *
 * @author Jeroen Desloovere <jeroen@siesqo.be>
 */
class ImageFolder
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var string
     */
    private $folder;

    /**
     * Construct
     *
     * @param string $path
     * @param string $folder
     */
    public function __construct($path, $folder)
    {
        $this->path = $path;
        $this->folder = $folder;
    }

    /**
     * To string
     *
     * @return string
     */
    public function __toString()
    {
        return $this->path . '/' . $this->folder;
    }

    /**
     * Get folder
     *
     * @return string
     */
    public function getFolder()
    {
        return $this->folder;
    }

    /**
     * Get Path
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }
}
