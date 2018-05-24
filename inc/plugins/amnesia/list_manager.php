<?php

namespace amnesia;

class ListManager
{
    public $orderColumns;
    public $orderColumnsAliases;
    public $orderDirections;
    public $defaultOrderDirection;
    public $orderExtend;
    public $itemsNum;
    public $perPage;
    public $inputEnabled;
    public $inputPrefix;

    public $orderColumn;
    public $orderColumnAlias;
    public $orderDirection;
    public $pagesNum;
    public $page;
    public $limitStart;
    public $limit;

    private $mybb;
    private $baseUrl;
    private $inAcp;

    public function __construct($data, $manualDetect = false)
    {
        $this->mybb = $data['mybb'];
        $this->baseUrl = $data['baseurl'];

        $this->orderColumns = [];
        $this->orderColumnsAliases = [];

        if (!empty($data['order_columns'])) {
            foreach ($data['order_columns'] as $key => $value) {
                if (is_numeric($key)) {
                    $this->orderColumns[] = $value;
                } else {
                    $this->orderColumns[] = $key;
                    $this->orderColumnsAliases[$value] = $key;
                }
            }
        }

        $this->orderDirections = ['asc', 'desc'];

        if (isset($data['order_dir'])) {
            $this->defaultOrderDirection = $data['order_dir'];
        } else {
            $this->defaultOrderDirection = 'asc';
        }

        if (isset($data['order_extend'])) {
            $this->orderExtend = $data['order_extend'];
        }

        if (isset($data['items_num'])) {
            $this->itemsNum = (int)$data['items_num'];
        }

        if (isset($data['per_page'])) {
            $this->perPage = (int)$data['per_page'];
        }

        if (isset($data['input_prefix'])) {
            $this->inputPrefix = $data['input_prefix'];
        } else {
            $this->inputPrefix = null;
        }

        if (isset($data['input_enabled'])) {
            $this->inputEnabled = $data['input_enabled'];
        } else {
            $this->inputEnabled = true;
        }

        $this->inAcp = defined('IN_ADMINCP');

        if (!$manualDetect) {
            $this->detect();
        }
    }

    public function link($column, $title)
    {
        if ($this->orderDirection == 'asc') {
            $linkOrder = 'desc';
            $pointer = '&uarr;';
        } else {
            $linkOrder = 'asc';
            $pointer = '&darr;';
        }

        if ($column == $this->orderColumn || $column == $this->orderColumnAlias) {
            $active = true;
        } else {
            $active = false;
            $pointer = null;
        }

        return '<a href="' . $this->urlWithSortParameters($column, $linkOrder) . '"' . ($active ? ' class="active"' : null) . '>' . $title . ' ' .  $pointer . '</a>';
    }

    public function pagination()
    {
        if ($this->perPage > 0 && $this->itemsNum > $this->perPage) {
            if ($this->inAcp) {
                return draw_admin_pagination(
                    $this->page,
                    $this->perPage,
                    $this->itemsNum,
                    $this->urlWithSortParameters()
                );
            } else {
                return multipage($this->itemsNum, $this->perPage, $this->page, $this->urlWithSortParameters());
            }
        } else {
            return null;
        }
    }

    public function sql()
    {
        return $this->orderSql() . " " . $this->limitSql();
    }

    public function orderSql($orderSyntax = true)
    {
        $sql = null;

        if ($this->orderColumn && $this->orderDirection) {
            $sql .= "`" . $this->orderColumn . "` " . strtoupper($this->orderDirection);

            if ($this->orderExtend) {
                $sql .= ($sql ? ', ' : null) . $this->orderExtend;
            }
        }

        if ($sql && $orderSyntax) {
            $sql = "ORDER BY " . $sql;
        }

        return $sql;
    }

    public function limitSql($limitSyntax = true)
    {
        if ($this->limit) {
            if ($limitSyntax) {
                return "LIMIT " . $this->limitStart . ", " . $this->limit;
            } else {
                return [
                    'limit_start' => $this->limitStart,
                    'limit' => $this->limit,
                ];
            }
        } else {
            return null;
        }
    }

    public function queryOptions()
    {
        return [
            'order_by' => $this->orderSql(false),
            'limit' => $this->limit,
            'limit_start' => $this->limitStart,
        ];
    }

    public function detect()
    {
        // sorting
        if ($this->orderColumns) {
            if (
                $this->inputEnabled &&
                $this->getInput('sortby') !== null &&
                in_array($this->getInput('sortby'), $this->orderColumns)
            ) {
                $this->setOrderColumn($this->getInput('sortby'));
            } elseif (!$this->orderColumn) {
                $this->setOrderColumn($this->orderColumns[0]);
            }
        }

        if (
            $this->inputEnabled &&
            $this->getInput('order') !== null &&
            in_array($this->getInput('order'), $this->orderDirections)
        ) {
            $this->setOrderDirection($this->getInput('order'));
        } elseif (!$this->orderDirection) {
            $this->setOrderDirection($this->defaultOrderDirection);
        }

        // pagination
        if ($this->perPage) {
            if ($this->itemsNum < 0) {
                $this->itemsNum = 0;
            }

            if ($this->perPage < 1) {
                $this->pagesNum = 0;
            } else {
                $this->pagesNum = ceil($this->itemsNum / $this->perPage);
            }

            if (!$this->page) {
                if (
                    $this->inputEnabled &&
                    $this->getInput('page') !== null &&
                    (int)$this->getInput('page') > 0 &&
                    (int)$this->getInput('page') <= $this->pagesNum
                ) {
                    $this->page = (int)$this->getInput('page');
                } else {
                    $this->page = 1;
                }
            }

            $this->limitStart = ($this->page - 1) * $this->perPage;
            $this->limit = $this->perPage;
        }
    }

    public function urlWithSortParameters($column = false, $linkOrder = false)
    {
        if ($column === false) {
            $column = $this->orderColumnAlias ?? $this->orderColumn;
        }

        if ($linkOrder === false) {
            $linkOrder = $this->orderDirection;
        }

        return $this->baseUrl . (strpos($this->baseUrl, '?') !== false ? '&' : '?') . $this->inputPrefix . 'sortby=' . $column . '&' . $this->inputPrefix . 'order=' . $linkOrder;
    }

    public function setOrderColumn($columnName)
    {
        if (in_array($columnName, $this->orderColumns)) {
            if ($aliasedColumn = array_search($columnName, $this->orderColumnsAliases)) {
                $this->orderColumn = $aliasedColumn;
                $this->orderColumnAlias = $columnName;
            } else {
                $this->orderColumn = $columnName;
                $this->orderColumnAlias = null;
            }

            return true;
        } else {
            return false;
        }
    }

    public function setOrderDirection($direction)
    {
        if (in_array($direction, $this->orderDirections)) {
            $this->orderDirection = $direction;

            return true;
        } else {
            return false;
        }
    }

    public function setOrder($column, $direction)
    {
        $this->setOrderColumn($column);
        $this->setOrderDirection($direction);
    }

    private function getInput($name)
    {
        return isset($this->mybb->input[$this->inputPrefix . $name])
            ? $this->mybb->input[$this->inputPrefix . $name]
            : null
            ;
    }
}
