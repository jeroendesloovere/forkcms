<?php

namespace Backend\Core\Engine;

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;

use Common\Uri as CommonUri;

use Backend\Core\Engine\Model as BackendModel;
use Backend\Core\Engine\ImageFolders as ImageFolders;

/**
 * This class represents a image-object
 *
 * @author Jeroen Desloovere <jeroen@siesqo.be>
 */
class Image
{
    /**
     * The data, when a existing meta-record is loaded
     *
     * @var array
     */
    protected $data;

    /**
     * The folders
     *
     * @var ImageFolders
     */
    protected $folders;

    /**
     * The form instance
     *
     * @var Form
     */
    protected $frm;

    /**
     * @param Form   $form          An instance of Form, the elements will be parsed in here.
     * @param array $folders
     */
    public function __construct(
        Form $form,
        ImageFolders $folders
    ) {
        // set form instance
        $this->frm = $form;

        // set folders
        $this->folders = $folders;

        // load the form
        $this->loadForm();
    }


    /**
     * Add all element into the form
     */
    protected function loadForm()
    {
        // is the form submitted?
        if ($this->frm->isSubmitted()) {
            /**
             * If the fields are disabled we don't have any values in the post.
             * When an error occurs in the other fields of the form the meta-fields would be cleared
             * therefore we alter the POST so it contains the initial values.
             */
            if (!isset($_POST['image'])) {
                $_POST['image'] = (isset($this->data['image'])) ? $this->data['image'] : null;
            }
            if (!isset($_POST['delete_image'])) {
                $_POST['delete_image'] = (isset($this->data['delete_image'])) ? $this->data['description'] : null;
            }
        }

        // add page title elements into the form
        $this->frm->addImage(
            'image',
            (isset($this->data['image'])) ? $this->data['image'] : null
        );
        $this->frm->addCheckbox('delete_image');
    }

    /**
     * Saves the meta object
     *
     * @param bool $update Should we update the record or insert a new one.
     * @throws Exception If no meta id was provided.
     * @return int
     */
    public function save($update = false)
    {
        if ($this->id instanceof MetaEntity) {
            return $this->getUpdated();
        }

        $meta = $this->getUpdatedData();

        $db = BackendModel::getContainer()->get('database');

        if ((bool) $update) {
            if ($this->id === null) {
                throw new Exception('No metaID specified.');
            }
            $db->update('meta', $meta, 'id = ?', array($this->id));

            return $this->id;
        } else {
            $id = (int) $db->insert('meta', $meta);

            return $id;
        }
    }

    /**
     * Get updated data
     */
    protected function getUpdatedData()
    {
        // init image path
        $imageName = $this->record['image'];

        // init the image path
        $imagePath = $this->folders->getFolder();

        // create folders if needed
        $fs = new Filesystem();
        foreach ($this->folders->getAll() as $imageFolder) {
            if (!$fs->exists((string) $imageFolder)) {
                $fs->mkdir((string) $imageFolder);
            }
        }

        // if the image should be deleted
        if ($this->frm->getField('delete_image')->isChecked()) {
            foreach ($this->folders->getAll() as $imageFolder) {
                $filename = (string) $imageFolder . $imageName;

                if (is_file($filename)) {
                   $fs->remove($filename);
                }
            }

            // reset the name
            $imageName = null;
        }
// @todo: hier bezig
        // new image given?
        if ($this->frm->getField('image')->isFilled()) {
            $filename = $imagePath . '/source/' . $this->record['image'];
            if (is_file($filename)) {
                $fs->remove($filename);
                BackendModel::deleteThumbnails($imagePath, $this->record['image']);
            }

            // build the image name
            $item['image'] = $this->meta->getURL() . '-' . BL::getWorkingLanguage() . '.' . $this->frm->getField('image')->getExtension();

            // upload the image & generate thumbnails
            $this->frm->getField('image')->generateThumbnails($imagePath, $item['image']);
        } elseif ($item['image'] != null) {
            // rename the old image
            $image = new File($imagePath . '/source/' . $item['image']);
            $newName = $this->meta->getURL() . '-' . BL::getWorkingLanguage() . '.' . $image->getExtension();

            // only change the name if there is a difference
            if ($newName != $item['image']) {
                // loop folders
                foreach (BackendModel::getThumbnailFolders($imagePath, true) as $folder) {
                    // move the old file to the new name
                    $fs->rename($folder['path'] . '/' . $item['image'], $folder['path'] . '/' . $newName);
                }

                // assign the new name to the database
                $item['image'] = $newName;
            }
        }

        return $data;
    }

    /**
     * Validates the form
     * It checks if there is a value when a checkbox is checked
     */
    public function validate()
    {
        // page title overwrite is checked
        if ($this->frm->getField('page_title_overwrite')->isChecked()) {
            $this->frm->getField('page_title')->isFilled(Language::err('FieldIsRequired'));
        }

        // meta description overwrite is checked
        if ($this->frm->getField('meta_description_overwrite')->isChecked()) {
            $this->frm->getField('meta_description')->isFilled(Language::err('FieldIsRequired'));
        }

        // meta keywords overwrite is checked
        if ($this->frm->getField('meta_keywords_overwrite')->isChecked()) {
            $this->frm->getField('meta_keywords')->isFilled(Language::err('FieldIsRequired'));
        }

        // URL overwrite is checked
        if ($this->frm->getField('url_overwrite')->isChecked()) {
            $this->frm->getField('url')->isFilled(Language::err('FieldIsRequired'));
            $URL = \SpoonFilter::htmlspecialcharsDecode($this->frm->getField('url')->getValue());
            $generatedUrl = $this->generateURL($URL);

            // check if urls are different
            if (CommonUri::getUrl($URL) != $generatedUrl) {
                $this->frm->getField('url')->addError(
                    Language::err('URLAlreadyExists')
                );
            }
        }

        // if the form was submitted correctly the data array should be populated
        if ($this->frm->isCorrect()) {
            // get meta keywords
            if ($this->frm->getField('meta_keywords_overwrite')->isChecked()) {
                $keywords = $this->frm->getField('meta_keywords')->getValue();
            } else {
                $keywords = $this->frm->getField($this->baseFieldName)->getValue();
            }

            // get meta description
            if ($this->frm->getField('meta_description_overwrite')->isChecked()) {
                $description = $this->frm->getField('meta_description')->getValue();
            } else {
                $description = $this->frm->getField($this->baseFieldName)->getValue();
            }

            // get page title
            if ($this->frm->getField('page_title_overwrite')->isChecked()) {
                $title = $this->frm->getField('page_title')->getValue();
            } else {
                $title = $this->frm->getField($this->baseFieldName)->getValue();
            }

            // get URL
            if ($this->frm->getField('url_overwrite')->isChecked()) {
                $URL = \SpoonFilter::htmlspecialcharsDecode($this->frm->getField('url')->getValue());
            } else {
                $URL = \SpoonFilter::htmlspecialcharsDecode($this->frm->getField($this->baseFieldName)->getValue());
            }

            // get the real URL
            $URL = $this->generateURL($URL);

            // get meta custom
            if ($this->custom && $this->frm->getField('meta_custom')->isFilled()) {
                $custom = $this->frm->getField('meta_custom')->getValue();
            } else {
                $custom = null;
            }

            // set data
            $this->data['keywords'] = $keywords;
            $this->data['keywords_overwrite'] = ($this->frm->getField('meta_keywords_overwrite')->isChecked(
            )) ? 'Y' : 'N';
            $this->data['description'] = $description;
            $this->data['description_overwrite'] = ($this->frm->getField('meta_description_overwrite')->isChecked(
            )) ? 'Y' : 'N';
            $this->data['title'] = $title;
            $this->data['title_overwrite'] = ($this->frm->getField('page_title_overwrite')->isChecked()) ? 'Y' : 'N';
            $this->data['url'] = $URL;
            $this->data['url_overwrite'] = ($this->frm->getField('url_overwrite')->isChecked()) ? 'Y' : 'N';
            $this->data['custom'] = $custom;
            if ($this->frm->getField('seo_index')->getValue() == 'none') {
                unset($this->data['data']['seo_index']);
            } else {
                $this->data['data']['seo_index'] = $this->frm->getField('seo_index')->getValue();
            }
            if ($this->frm->getField('seo_follow')->getValue() == 'none') {
                unset($this->data['data']['seo_follow']);
            } else {
                $this->data['data']['seo_follow'] = $this->frm->getField('seo_follow')->getValue();
            }
        }
    }
}
