<?php

namespace ForkCMS\Backend\Modules\Tags\Actions;

use ForkCMS\Backend\Core\Engine\Base\ActionIndex as BackendBaseActionIndex;
use ForkCMS\Backend\Core\Engine\Authentication as BackendAuthentication;
use ForkCMS\Backend\Core\Engine\DataGridDatabase as BackendDataGridDatabase;
use ForkCMS\Backend\Core\Language\Language as BL;
use ForkCMS\Backend\Core\Engine\Model as BackendModel;
use ForkCMS\Backend\Modules\Tags\Engine\Model as BackendTagsModel;

/**
 * This is the index-action, it will display the overview of tags
 */
class Index extends BackendBaseActionIndex
{
    public function execute(): void
    {
        parent::execute();
        $this->loadDataGrid();
        $this->parse();
        $this->display();
    }

    private function loadDataGrid(): void
    {
        // create datagrid
        $this->dataGrid = new BackendDataGridDatabase(
            BackendTagsModel::QUERY_DATAGRID_BROWSE,
            [BL::getWorkingLanguage()]
        );

        // header labels
        $this->dataGrid->setHeaderLabels([
            'tag' => \SpoonFilter::ucfirst(BL::lbl('Name')),
            'num_tags' => \SpoonFilter::ucfirst(BL::lbl('Amount')),
        ]);

        // sorting columns
        $this->dataGrid->setSortingColumns(['tag', 'num_tags'], 'num_tags');
        $this->dataGrid->setSortParameter('desc');

        // add the multicheckbox column
        $this->dataGrid->setMassActionCheckboxes('check', '[id]');

        // add mass action dropdown
        $ddmMassAction = new \SpoonFormDropdown(
            'action',
            ['delete' => BL::lbl('Delete')],
            'delete',
            false,
            'form-control',
            'form-control danger'
        );
        $ddmMassAction->setOptionAttributes('delete', [
            'data-target' => '#confirmDelete',
        ]);
        $this->dataGrid->setMassAction($ddmMassAction);

        // add attributes, so the inline editing has all the needed data
        $this->dataGrid->setColumnAttributes('tag', ['data-id' => '{\'id\':[id]}']);

        // check if this action is allowed
        if (BackendAuthentication::isAllowedAction('Edit')) {
            // add column
            $this->dataGrid->addColumn(
                'edit',
                null,
                BL::lbl('Edit'),
                BackendModel::createUrlForAction('Edit') . '&amp;id=[id]',
                BL::lbl('Edit')
            );
        }
    }

    protected function parse(): void
    {
        parent::parse();

        $this->template->assign('dataGrid', $this->dataGrid->getContent());
    }
}
