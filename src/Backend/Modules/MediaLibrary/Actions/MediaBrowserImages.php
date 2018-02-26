<?php

namespace ForkCMS\Backend\Modules\MediaLibrary\Actions;

use ForkCMS\Backend\Core\Language\Language;
use ForkCMS\Backend\Modules\MediaLibrary\Domain\MediaFolder\MediaFolder;
use ForkCMS\Backend\Modules\MediaLibrary\Domain\MediaGroup\MediaGroupType;
use ForkCMS\Backend\Modules\MediaLibrary\Domain\MediaItem\MediaItemSelectionDataGrid;
use ForkCMS\Backend\Modules\MediaLibrary\Domain\MediaItem\Type;

class MediaBrowserImages extends MediaBrowser
{
    public function execute(): void
    {
        $this->mediaFolder = $this->getMediaFolder();

        parent::parseJsFiles();
        $this->parse();
        $this->display('/' . $this->getModule() . '/Layout/Templates/MediaBrowser.html.twig');
    }

    protected function parse(): void
    {
        // Parse files necessary for the media upload helper
        MediaGroupType::parseFiles();

        parent::parseDataGrids($this->mediaFolder);

        /** @var int|null $mediaFolderId */
        $mediaFolderId = ($this->mediaFolder instanceof MediaFolder) ? $this->mediaFolder->getId() : null;

        $this->template->assign('folderId', $mediaFolderId);
        $this->template->assign('tree', $this->get('media_library.manager.tree_media_browser_images')->getHTML());
        $this->header->addJsData('MediaLibrary', 'openedFolderId', $mediaFolderId);
    }

    protected function getDataGrids(MediaFolder $mediaFolder = null): array
    {
        return array_map(
            function ($type) use ($mediaFolder) {
                $dataGrid = MediaItemSelectionDataGrid::getDataGrid(
                    Type::fromString($type),
                    ($mediaFolder !== null) ? $mediaFolder->getId() : null
                );

                return [
                    'label' => Language::lbl('MediaMultiple' . ucfirst($type)),
                    'tabName' => 'tab' . ucfirst($type),
                    'mediaType' => $type,
                    'html' => $dataGrid->getContent(),
                    'numberOfResults' => $dataGrid->getNumResults(),
                ];
            },
            [Type::IMAGE]
        );
    }
}
