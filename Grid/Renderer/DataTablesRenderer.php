<?php

namespace Dtc\GridBundle\Grid\Renderer;

use Dtc\GridBundle\Grid\Column\AbstractGridColumn;

class DataTablesRenderer extends AbstractJqueryRenderer
{
    public static $defaultOptions = array(
        'processing' => true,
        'searchDelay' => 350,
        'table_attr' => array(
                'class' => 'display table table-striped table-bordered small-font',
            ),
        'serverSide' => true,
        'language' => array(
            'lengthMenu' => '_MENU_ records per page',
        ),
    );

    protected $dataTablesCss = [];
    protected $dataTablesJs = [];
    private $localCss = [];
    private $localJs = [];

    const MODE_AJAX = 1;
    const MODE_SERVER = 2;

    protected $mode = 1;

    public function setMode($mode)
    {
        $this->mode = $mode;
    }

    /**
     * Set the type (bootstrap, bootstrap4, foundation, etc.).
     *
     * @param $type
     */
    public function setDataTablesCss($css)
    {
        $this->dataTablesCss = $css;
    }

    public function getDataTablesCss()
    {
        return $this->dataTablesCss;
    }

    public function setDataTablesJs($js)
    {
        $this->dataTablesJs = $js;
    }

    public function getDataTablesJs()
    {
        return $this->dataTablesJs;
    }

    public function setDataTablesLocalCss(array $localCss)
    {
        $this->localCss = $localCss;
    }

    public function setDataTablesLocalJs(array $localJs)
    {
        $this->localJs = $localJs;
    }

    public function getDataTablesLocalCss()
    {
        return $this->localCss;
    }

    public function getDataTablesLocalJs()
    {
        return $this->localJs;
    }

    public function setDataTablesClass($class)
    {
        $this->options['table_attr']['class'] = $class;
    }

    public function getDataTablesClass()
    {
        return isset($this->options['table_attr']['class']) ? $this->options['table_attr']['class'] : null;
    }

    /**
     * @param array|null $params
     */
    public function getParams(array &$params = null)
    {
        if (null === $params) {
            $params = [];
        }
        parent::getParams($params);
        $params['dtc_grid_datatables_css'] = $this->dataTablesCss;
        $params['dtc_grid_datatables_js'] = $this->dataTablesJs;
        $params['dtc_grid_local_css'] = $this->localCss;
        $params['dtc_grid_local_js'] = $this->localJs;

        return $params;
    }

    protected function afterBind()
    {
        $id = $this->gridSource->getDivId();
        $this->options['pager'] = "{$id}-pager";

        $fields = array_keys($this->gridSource->getColumns());

        // We need to pass filter information here.
        $params = array(
               'id' => $this->gridSource->getId(),
               'renderer' => 'datatables',
               'filter' => $this->gridSource->getFilter(),
               'parameters' => $this->gridSource->getParameters(),
               'order' => $this->gridSource->getOrderBy(),
               'fields' => $fields,
        );

        $sortInfo = $this->gridSource->getDefaultSort();
        $defaultSortColumn = isset($sortInfo['column']) ? $sortInfo['column'] : null;
        $defaultSortDirection = isset($sortInfo['direction']) ? $sortInfo['direction'] : 'ASC';
        $defaultSortDirection = strtolower($defaultSortDirection);
        $defaultSortColumnIdx = 0;

        $url = $this->router->generate('dtc_grid_data', $params);
        $this->options['sAjaxSource'] = $url;

        $columnsDef = array();
        /** @var AbstractGridColumn $column */
        $idx = 0;
        foreach ($this->gridSource->getColumns() as $index => $column) {
            $info = array();
            $name = $column->getField();
            $info['bSortable'] = $column->getOption('sortable') ? true : false;
            $info['sName'] = $name;

            if ($width = $column->getOption('width')) {
                $info['sWidth'] = $width;
            }

            $info['aTargets'] = array($index);
            $info = array_merge($info, $column->getOptions());
            $columnsDef[] = $info;
            if ($index === $defaultSortColumn) {
                $defaultSortColumnIdx = $idx;
            }
            ++$idx;
        }

        $this->options['order'] = [[$defaultSortColumnIdx, $defaultSortDirection]];
        $this->options['aoColumns'] = $columnsDef;
    }

    public function getData()
    {
        $columns = $this->gridSource->getColumns();
        $gridSource = $this->gridSource;
        $records = $gridSource->getRecords();
        $count = $gridSource->getCount();

        $retVal = array(
                'page' => $gridSource->getPager()
                    ->getCurrentPage(),
                'total_pages' => $gridSource->getPager()
                    ->getTotalPages(),
                'iTotalRecords' => (int) $count,
                'iTotalDisplayRecords' => $count,
                'id' => $gridSource->getId(), // unique id
        );

        $data = array();
        foreach ($records as $record) {
            $info = array();
            /** @var AbstractGridColumn $column */
            foreach ($columns as $column) {
                if (method_exists($column, 'setRouter')) {
                    $column->setRouter($this->router);
                }
                if (method_exists($column, 'setGridSourceId')) {
                    $column->setGridSourceId($gridSource->getId());
                }
                $info[] = $column->format($record, $gridSource);
            }

            $data[] = $info;
        }

        $retVal['aaData'] = $data;

        return $retVal;
    }

    public function render()
    {
        $id = $this->gridSource->getDivId();

        $options = $this->options;
        unset($options['table_attr']);

        $params = array(
                'options' => $options,
                'table_attr' => $this->options['table_attr'],
                'columns' => $this->gridSource->getColumns(),
                'id' => $id,
        );

        $template = '@DtcGrid/Grid/datatables.html.twig';

        return $this->twig->render($template, $params);
    }
}
