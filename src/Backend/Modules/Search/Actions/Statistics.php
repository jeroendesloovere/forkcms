<?php

namespace App\Backend\Modules\Search\Actions;

use App\Backend\Core\Engine\Base\Action;
use App\Backend\Core\Engine\DataGridDatabase as BackendDataGridDatabase;
use App\Backend\Core\Engine\DataGridFunctions as BackendDataGridFunctions;
use App\Backend\Core\Language\Language as BL;
use App\Backend\Modules\Search\Engine\Model as BackendSearchModel;

/**
 * This is the statistics-action, it will display the overview of search statistics
 */
class Statistics extends Action
{
    public function execute(): void
    {
        parent::execute();
        $this->showDataGrid();
        $this->display();
    }

    private function showDataGrid(): void
    {
        $dataGrid = new BackendDataGridDatabase(
            BackendSearchModel::QUERY_DATAGRID_BROWSE_STATISTICS,
            [BL::getWorkingLanguage()]
        );
        $dataGrid->setColumnsHidden(['data']);
        $dataGrid->addColumn('referrer', BL::lbl('Referrer'));
        $dataGrid->setHeaderLabels(['time' => \SpoonFilter::ucfirst(BL::lbl('SearchedOn'))]);

        // set column function
        $dataGrid->setColumnFunction([__CLASS__, 'parseRefererInDataGrid'], '[data]', 'referrer');
        $dataGrid->setColumnFunction(
            [new BackendDataGridFunctions(), 'getLongDate'],
            ['[time]'],
            'time',
            true
        );
        $dataGrid->setColumnFunction('htmlspecialchars', ['[term]'], 'term');

        $dataGrid->setSortingColumns(['time', 'term'], 'time');
        $dataGrid->setSortParameter('desc');

        $this->template->assign('dataGrid', $dataGrid->getContent());
    }

    public static function parseRefererInDataGrid(string $data): string
    {
        $data = unserialize($data);
        if (!isset($data['server']['HTTP_REFERER'])) {
            return '';
        }

        $referrer = htmlspecialchars($data['server']['HTTP_REFERER']);

        return '<a href="' . $referrer . '">' . $referrer . '</a>';
    }
}
