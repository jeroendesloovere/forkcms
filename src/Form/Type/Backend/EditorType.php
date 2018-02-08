<?php

namespace App\Form\Type\Backend;

use Backend\Core\Engine\Model;
use App\Component\Locale\BackendLanguage;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class EditorType extends TextareaType
{
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults(['attr' => ['class' => 'inputEditor']]);

        if (!Model::has('header')) {
            return;
        }
        // add the needed javascript to the header;
        $header = Model::get('header');
        // we add JS because we need CKEditor
        $header->addJS('ckeditor/ckeditor.js', 'Core', false);
        $header->addJS('ckeditor/adapters/jquery.js', 'Core', false);
        $header->addJS('ckfinder/ckfinder.js', 'Core', false);

        // add the internal link lists-file
        if (is_file(FRONTEND_CACHE_PATH . '/Navigation/editor_link_list_' . BackendLanguage::getWorkingLanguage() . '.js')) {
            $timestamp = @filemtime(
                FRONTEND_CACHE_PATH . '/Navigation/editor_link_list_' . BackendLanguage::getWorkingLanguage() . '.js'
            );
            $header->addJS(
                '/var/frontend/Navigation/editor_link_list_' . BackendLanguage::getWorkingLanguage(
                ) . '.js?m=' . $timestamp,
                null,
                false,
                true,
                false
            );
        }
    }

    public function getParent(): string
    {
        return TextareaType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'editor';
    }
}