<?php

namespace Backend\Modules\MediaLibrary\Domain\MediaItem;

use Backend\Core\Engine\Model;
use Backend\Core\Language\Language;
use Backend\Core\Language\Locale as BackendLocale;
use Backend\Modules\MediaLibrary\Component\StorageProvider\LiipImagineBundleStorageProviderInterface;
use Backend\Modules\MediaLibrary\Component\StorageProvider\StorageProviderInterface;
use Backend\Modules\MediaLibrary\Domain\MediaFolder\MediaFolder;
use Backend\Modules\MediaLibrary\Domain\MediaGroupMediaItem\MediaGroupMediaItem;
use Backend\Modules\MediaLibrary\Domain\MediaItemTranslation\Exception\MediaItemTranslationNotFound;
use Backend\Modules\MediaLibrary\Domain\MediaItemTranslation\MediaItemTranslation;
use Common\Locale;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use JsonSerializable;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\File\File;

/**
 * MediaItem
 *
 * @ORM\Entity(repositoryClass="Backend\Modules\MediaLibrary\Domain\MediaItem\MediaItemRepository")
 * @ORM\HasLifecycleCallbacks
 */
class MediaItem implements JsonSerializable
{
    /**
     * @var string
     *
     * @ORM\Id()
     * @ORM\GeneratedValue(strategy="UUID")
     * @ORM\Column(type="guid")
     */
    private $id;

    /**
     * @var MediaFolder
     *
     * @ORM\ManyToOne(
     *      targetEntity="Backend\Modules\MediaLibrary\Domain\MediaFolder\MediaFolder",
     *      inversedBy="items",
     *      cascade="persist"
     * )
     * @ORM\JoinColumn(
     *      name="mediaFolderId",
     *      referencedColumnName="id",
     *      onDelete="cascade"
     * )
     */
    protected $folder;

    /**
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    protected $userId;

    /**
     * @var StorageType
     *
     * @ORM\Column(type="media_item_storage_type", options={"default"="local"})
     */
    protected $storageType;

    /**
     * @var Type
     *
     * @ORM\Column(type="media_item_type")
     */
    protected $type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $mime;

    /**
     * @var string
     *
     * @ORM\Column(type="string", nullable=true)
     */
    protected $shardingFolderName;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    protected $url;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $size;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $width;

    /**
     * @var int
     *
     * @ORM\Column(type="integer", nullable=true)
     */
    protected $height;

    /**
     * @var AspectRatio
     *
     * @ORM\Column(type="media_item_aspect_ratio", nullable=true)
     */
    protected $aspectRatio;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    protected $createdOn;

    /**
     * @var \DateTime
     *
     * @ORM\Column(type="datetime")
     */
    protected $editedOn;

    /**
     * @var ArrayCollection|MediaItemTranslation[]
     *
     * @ORM\OneToMany(
     *     targetEntity="\Backend\Modules\MediaLibrary\Domain\MediaItemTranslation\MediaItemTranslation",
     *     mappedBy="mediaItem",
     *     orphanRemoval=true,
     *     cascade={"persist"},
     *     fetch="EAGER"
     * )
     */
    private $translations;

    /**
     * @var ArrayCollection|MediaGroupMediaItem[]
     *
     * @ORM\OneToMany(
     *     targetEntity="Backend\Modules\MediaLibrary\Domain\MediaGroupMediaItem\MediaGroupMediaItem",
     *     mappedBy="item",
     *     cascade={"persist","merge"},
     *     orphanRemoval=true
     * )
     */
    protected $groups;

    private function __construct(
        string $title,
        string $url,
        Type $type,
        StorageType $storageType,
        MediaFolder $folder,
        int $userId
    ) {
        $this->folder = $folder;
        $this->userId = $userId;
        $this->type = $type;
        $this->storageType = $storageType;
        $this->url = $url;
        $this->createdOn = new \DateTime();
        $this->editedOn = new \DateTime();
        $this->translations = new ArrayCollection();
        $this->groups = new ArrayCollection();

        foreach (array_keys(Language::getWorkingLanguages()) as $workingLanguage) {
            $this->addTranslation(
                new MediaItemTranslation(
                    BackendLocale::fromString($workingLanguage),
                    $this,
                    $title
                )
            );
        }
    }

    public static function createFromLocalStorageType(
        string $path,
        MediaFolder $folder,
        int $userId
    ): self {
        $file = self::getFileFromPath($path);

        $mediaItem = new self(
            self::getTitleFromFile($file),
            $file->getFilename(),
            self::getTypeFromFile($file),
            StorageType::local(),
            $folder,
            $userId
        );

        $mediaItem->setForFile($file);
        $mediaItem->setResolutionFromPath($path);

        return $mediaItem;
    }

    private function setForFile(File $file)
    {
        $this->mime = $file->getMimeType();
        $this->size = $file->getSize();

        // Define sharding folder (getPath gets the path without the trailing slash)
        $this->shardingFolderName = basename($file->getPath());
    }

    private function setResolutionFromPath(string $path)
    {
        if ($this->getType()->isImage()) {
            try {
                [$width, $height] = getimagesize($path);
            } catch (Exception $e) {
                throw new Exception(
                    'Error happened when creating MediaItem from path "' . $path . '". The error = ' . $e->getMessage()
                );
            }

            $this->setResolution($width, $height);
        }
    }

    public static function createFromMovieUrl(
        StorageType $movieStorageType,
        string $movieId,
        string $movieTitle,
        MediaFolder $folder,
        int $userId
    ): MediaItem {
        return new self(
            $movieTitle,
            $movieId,
            Type::movie(),
            $movieStorageType,
            $folder,
            $userId
        );
    }

    public static function fromDataTransferObject(MediaItemDataTransferObject $mediaItemDataTransferObject): ?MediaItem
    {
        if (!$mediaItemDataTransferObject->hasExistingMediaItem()) {
            throw new RuntimeException('This method can not be used to create a new media item');
        }

        $mediaItem = $mediaItemDataTransferObject->getMediaItemEntity();

        $mediaItem->folder = $mediaItemDataTransferObject->folder;
        $mediaItem->userId = $mediaItemDataTransferObject->userId;
        $mediaItem->url = $mediaItemDataTransferObject->url;

        foreach ($mediaItemDataTransferObject->translations as $mediaItemTranslationDataTransferObject) {
            $mediaItemTranslationDataTransferObject->setMediaItem($mediaItem);

            $mediaItem->addTranslation(MediaItemTranslation::fromDataTransferObject($mediaItemTranslationDataTransferObject));
        }

        return $mediaItem;
    }

    public function jsonSerialize(): array
    {
        $locale = BackendLocale::workingLocale();

        return [
            'id' => $this->id,
            'folder' => $this->folder,
            'userId' => $this->userId,
            'type' => (string) $this->type,
            'storageType' => (string) $this->storageType,
            'mime' => $this->mime,
            'shardingFolderName' => $this->shardingFolderName,
            'url' => $this->url,
            'fullUrl' => $this->getFullUrl(),
            'title' => $this->getTranslation($locale)->getTitle(),
            'caption' => $this->getTranslation($locale)->getCaption(),
            'hasLink' => $this->getTranslation($locale)->hasLink(),
            'link' => $this->getTranslation($locale)->getLink(),
            'description' => $this->getTranslation($locale)->getDescription(),
            'alt' => $this->getTranslation($locale)->getAlt(),
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'createdOn' => $this->createdOn->getTimestamp(),
            'editedOn' => $this->editedOn->getTimestamp(),
            'source' => $this->getWebPath(),
            'preview_source' => $this->getWebPath('backend'),
            'direct_url' => $this->getWebPath(),
            $this->type->getType() => true,
        ];
    }

    private static function getFileFromPath(string $path): File
    {
        try {
            // Define file from path
            $file = new File($path);
        } catch (FileNotFoundException $e) {
            throw new Exception(
                'This is not a valid file: "' . $path . '".'
            );
        }

        // We don't have a file
        if (!$file->isFile()) {
            throw new Exception(
                'The given source is not a file.'
            );
        }

        return $file;
    }

    private static function getTitleFromFile(File $file): string
    {
        return str_replace('.' . $file->getExtension(), '', $file->getFilename());
    }

    private static function getTypeFromFile(File $file): Type
    {
        return Type::fromMimeType($file->getMimeType());
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getFolder(): MediaFolder
    {
        return $this->folder;
    }

    public function setFolder(MediaFolder $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getStorageType(): StorageType
    {
        return $this->storageType;
    }

    public function setStorageType(StorageType $storageType)
    {
        $this->storageType = $storageType;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function getMime(): string
    {
        return $this->mime;
    }

    public function getScardingFolderName(): ?string
    {
        return $this->shardingFolderName;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getFullUrl(): string
    {
        return $this->getScardingFolderName() . '/' . $this->getUrl();
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setResolution(int $width, int $height): self
    {
        $this->width = $width;
        $this->height = $height;

        $this->refreshAspectRatio();

        return $this;
    }

    public function getWidth(): ?int
    {
        return $this->width;
    }

    public function getHeight(): ?int
    {
        return $this->height;
    }

    public function getCreatedOn(): \DateTime
    {
        return $this->createdOn;
    }

    public function getEditedOn(): \DateTime
    {
        return $this->editedOn;
    }

    public function getGroups(): Collection
    {
        return $this->groups;
    }

    public function hasGroups(): bool
    {
        return $this->groups->count() > 0;
    }

    public function getAbsolutePath(): string
    {
        return Model::get('media_library.manager.storage')->getStorageProvider(
            $this->getStorageType()
        )->getAbsolutePath($this);
    }

    public function getAbsoluteWebPath(): string
    {
        return Model::get('media_library.manager.storage')->getStorageProvider(
            $this->getStorageType()
        )->getAbsoluteWebPath($this);
    }

    public function getLinkHTML(): string
    {
        return Model::get('media_library.manager.storage')->getStorageProvider($this->getStorageType())->getLinkHTML(
            $this
        );
    }

    public function getIncludeHTML(): string
    {
        return Model::get('media_library.manager.storage')->getStorageProvider($this->getStorageType())->getIncludeHTML(
            $this
        );
    }

    public function getAspectRatio(): AspectRatio
    {
        return $this->aspectRatio;
    }

    public function getWebPath(string $liipImagineBundleFilter = null): string
    {
        /** @var StorageProviderInterface $storage */
        $storage = Model::get('media_library.manager.storage')->getStorageProvider($this->getStorageType());

        if (!$storage instanceof LiipImagineBundleStorageProviderInterface || $liipImagineBundleFilter === null) {
            return $storage->getWebPath($this);
        }

        return $storage->getWebPathWithFilter($this, $liipImagineBundleFilter);
    }

    private function refreshAspectRatio(): void
    {
        if ($this->height === null || $this->width === null) {
            $this->aspectRatio = null;

            return;
        }

        $this->aspectRatio = AspectRatio::fromWidthAndHeight($this->width, $this->height);
    }

    /**
     * @param Locale $locale
     * @return MediaItemTranslation when the translation does not exist
     * @throws \Exception
     */
    public function getTranslation(Locale $locale): MediaItemTranslation
    {
        if ($this->translations->isEmpty()) {
            throw MediaItemTranslationNotFound::forLocale($locale);
        }

        $translations = $this->translations->filter(
            function (MediaItemTranslation $translation) use ($locale) {
                return $translation->getLocale()->equals($locale);
            }
        );

        if ($translations->isEmpty()) {
            throw MediaItemTranslationNotFound::forLocale($locale);
        }

        return $translations->first();
    }

    public function getTranslations(): Collection
    {
        return $this->translations;
    }

    public function addTranslation(MediaItemTranslation $translation)
    {
        if ($this->translations->contains($translation)) {
            return;
        }

        $this->translations->add($translation);
    }

    public function removeTranslation(MediaItemTranslation $translation)
    {
        $this->translations->removeElement($translation);
    }

    /**
     * @ORM\PrePersist
     */
    public function onPrePersist()
    {
        $this->createdOn = $this->editedOn = new \Datetime();

        $this->refreshAspectRatio();
    }

    /**
     * @ORM\PreUpdate
     */
    public function onPreUpdate()
    {
        $this->editedOn = new \Datetime();

        $this->refreshAspectRatio();
    }
}
