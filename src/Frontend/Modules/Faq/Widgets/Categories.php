<?php

namespace ForkCMS\Frontend\Modules\Faq\Widgets;

use ForkCMS\Frontend\Core\Engine\Base\Widget as FrontendBaseWidget;
use ForkCMS\Frontend\Modules\Faq\Engine\Model as FrontendFaqModel;

/**
 * This is a widget with faq categories
 */
class Categories extends FrontendBaseWidget
{
    public function execute(): void
    {
        parent::execute();

        $this->loadTemplate();
        $this->parse();
    }

    private function parse(): void
    {
        $this->template->assign('widgetFaqCategories', FrontendFaqModel::getCategories());
    }
}
