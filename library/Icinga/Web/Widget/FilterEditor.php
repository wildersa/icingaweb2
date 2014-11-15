<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Web\Widget;

use Icinga\Data\Filter\Filter;
use Icinga\Data\Filter\FilterExpression;
use Icinga\Data\Filter\FilterChain;
use Icinga\Web\Url;
use Icinga\Application\Icinga;
use Icinga\Exception\ProgrammingError;
use Exception;

/**
 * Filter
 */
class FilterEditor extends AbstractWidget
{
    /**
     * The filter
     *
     * @var Filter
     */
    private $filter;

    protected $query;

    protected $url;

    protected $addTo;

    protected $cachedColumnSelect;

    protected $preserveParams = array();

    protected $ignoreParams = array();

    /**
     * @var string
     */
    private $selectedIdx;

    /**
     * Create a new FilterWidget
     *
     * @param Filter $filter Your filter
     */
    public function __construct($props)
    {
        if (array_key_exists('filter', $props)) {
            $this->setFilter($props['filter']);
        }
        if (array_key_exists('query', $props)) {
            $this->setQuery($props['query']);
        }
    }

    public function setFilter(Filter $filter)
    {
        $this->filter = $filter;
        return $this;
    }

    public function getFilter()
    {
        if ($this->filter === null) {
            $this->filter = Filter::fromQueryString((string) $this->url()->getParams()); 
        }
        return $this->filter;
    }

    public function setUrl($url)
    {
        $this->url = $url;
        return $this;
    }

    protected function url()
    {
        if ($this->url === null) {
            $this->url = Url::fromRequest();
        }
        return $this->url;
    }

    public function setQuery($query)
    {
        $this->query = $query;
        return $this;
    }

    public function ignoreParams()
    {
        $this->ignoreParams = func_get_args();
        return $this;
    }

    public function preserveParams()
    {
        $this->preserveParams = func_get_args();
        return $this;
    }

    protected function redirectNow($url)
    {
        $response = Icinga::app()->getFrontController()->getResponse();
        $response->redirectAndExit($url);
    }

    protected function select($name, $list, $selected, $attributes = null)
    {
        $view = $this->view();
        if ($attributes === null) {
            $attributes = '';
        } else {
            $attributes = $view->propertiesToString($attributes);
        }
        $html = sprintf(
            '<select name="%s"%s class="autosubmit">' . "\n",
            $view->escape($name),
            $attributes
        );

        foreach ($list as $k => $v) {
            $active = '';
            if ($k === $selected) {
                $active = ' selected="selected"';
            }
            $html .= sprintf(
                '  <option value="%s"%s>%s</option>' . "\n",
                $view->escape($k),
                $active,
                $view->escape($v)
            );
        }
        $html .= '</select>' . "\n\n";
        return $html;
    }

    protected function addFilterToId($id)
    {
        $this->addTo = $id;
        return $this;
    }

    protected function removeIndex($idx)
    {
        $this->selectedIdx = $idx;
        return $this;
    }

    protected function removeLink(Filter $filter)
    {
        return $this->view()->qlink(
            '',
            $this->url()->with('removeFilter', $filter->getId()),
            null,
            array(
                'title' => t('Click to remove this part of your filter'),
                'class' => 'icon-cancel'
            )
        );
    }

    protected function addLink(Filter $filter)
    {
        return $this->view()->qlink(
            '',
            $this->url()->with('addFilter', $filter->getId()),
            null,
            array(
                'title' => t('Click to add another filter'),
                'class' => 'icon-plus'
            )
        );
    }

    protected function stripLink(Filter $filter)
    {
        return $this->view()->qlink(
            '',
            $this->url()->with('stripFilter', $filter->getId()),
            null,
            array(
                'title' => t('Strip this filter'),
                'class' => 'icon-minus'
            )
        );
    }

    protected function cancelLink()
    {
        return $this->view()->qlink(
            '',
            $this->url()->without('addFilter'),
            null,
            array(
                'title' => t('Cancel this operation'),
                'class' => 'icon-cancel'
            )
        );
    }

    protected function renderFilter($filter, $level = 0)
    {
        if ($level === 0 && $filter->isChain() && $filter->isEmpty()) {
            return '<ul class="datafilter"><li style="background-color: #ffb">' . $this->renderNewFilter() . '</li></ul>';
        }

        if ($filter instanceof FilterChain) {
            return $this->renderFilterChain($filter, $level);
        } elseif ($filter instanceof FilterExpression) {
            return $this->renderFilterExpression($filter);
        } else {
            throw new ProgrammingError('Got a Filter being neither expression nor chain');
        }
    }

    protected function renderFilterChain(FilterChain $filter, $level)
    {
        $html = '<span class="handle"> </span>'
              . $this->selectOperator($filter)
              . $this->removeLink($filter)
              . ($filter->count() === 1 ? $this->stripLink($filter) : '')
              . $this->addLink($filter);

        if ($filter->isEmpty() && ! $this->addTo) {
            return $html;
        }

        $parts = array();
        foreach ($filter->filters() as $f) {
            $parts[] = '<li>' . $this->renderFilter($f, $level + 1) . '</li>';
        }

        if ($this->addTo && $this->addTo == $filter->getId()) {
            $parts[] = '<li style="background: #ffb">' . $this->renderNewFilter() .$this->cancelLink(). '</li>';
        }

        $class = $level === 0 ? ' class="datafilter"' : '';
        $html .= sprintf(
            "<ul%s>\n%s</ul>\n",
            $class,
            implode("", $parts)
        );
        return $html;
    }

    protected function renderFilterExpression(FilterExpression $filter)
    {
        if ($this->addTo && $this->addTo === $filter->getId()) {
            return 
                   preg_replace(
            '/ class="autosubmit"/',
            ' class="autofocus"',
        $this->selectOperator()
                )
                  . '<ul><li>'
                  . $this->selectColumn($filter)
                  . $this->selectSign($filter)
                  . $this->text($filter)
                  . $this->removeLink($filter)
                  . $this->addLink($filter)
                  . '</li><li style="background-color: #ffb">'
                  . $this->renderNewFilter() .$this->cancelLink()
                  . '</li></ul>'
                  ;
        } else {
            return $this->selectColumn($filter)
                 . $this->selectSign($filter)
                 . $this->text($filter)
                 . $this->removeLink($filter)
                 . $this->addLink($filter)
                 ;

        }
    }

    protected function text(Filter $filter = null)
    {
        $value = $filter === null ? '' : $filter->getExpression();
        if (is_array($value)) {
            $value = '(' . implode('|', $value) . ')';
        }
        return sprintf(
            '<input type="text" name="%s" value="%s" />',
            $this->elementId('value', $filter),
            $value
        );
    }

    protected function renderNewFilter()
    {
        $html = $this->selectColumn()
              . $this->selectSign()
              . $this->text();

        return preg_replace(
            '/ class="autosubmit"/',
            '',
            $html
        );
    }

    protected function arrayForSelect($array)
    {
        $res = array();
        foreach ($array as $k => $v) {
            if (is_int($k)) {
                $res[$v] = $v;
            } else {
                $res[$k] = $v;
            }
        }
        // sort($res);
        return $res;
    }

    protected function elementId($prefix, Filter $filter = null)
    {
        if ($filter === null) {
            return $prefix . '_new_' . ($this->addTo ?: '0');
        } else {
            return $prefix . '_' . $filter->getId();
        }
    }

    protected function selectOperator(Filter $filter = null)
    {
        $ops = array(
            'AND' => 'AND',
            'OR'  => 'OR',
            'NOT' => 'NOT'
        );

        return $this->select(
            $this->elementId('operator', $filter),
            $ops,
            $filter === null ? null : $filter->getOperatorName(),
            array('style' => 'width: 5em')
        );
    }

    protected function selectSign(Filter $filter = null)
    {
        $signs = array(
            '='  => '=',
            '!=' => '!=',
            '>'  => '>',
            '<'  => '<',
            '>=' => '>=',
            '<=' => '<=',
        );

        return $this->select(
            $this->elementId('sign', $filter),
            $signs,
            $filter === null ? null : $filter->getSign(),
            array('style' => 'width: 4em')
        );
    }

    protected function selectColumn(Filter $filter = null)
    {
        $active = $filter === null ? null : $filter->getColumn();

        if ($this->query === null) {
            return sprintf(
                '<input type="text" name="%s" value="%s" />',
                $this->elementId('column', $filter),
                $this->view()->escape($active) // Escape attribute?
            );
        }

        if ($this->cachedColumnSelect === null) {
            $this->cachedColumnSelect = $this->arrayForSelect($this->query->getColumns());
            asort($this->cachedColumnSelect);
        }
        $cols = $this->cachedColumnSelect;
        $seen = false;
        foreach ($cols as $k => & $v) {
            $v = str_replace('_', ' ', ucfirst($v));
            if ($k === $active) {
                $seen = true;
            }
        }

        if (!$seen) {
            $cols[$active] = str_replace('_', ' ', ucfirst(ltrim($active, '_')));
        }

        return $this->select($this->elementId('column', $filter), $cols, $active); 
    }

    public function renderSearch()
    {
        $html = ' <form method="post" class="inline" action="'
              . $this->url()
              . '"><input type="text" name="q" style="width: 8em" class="search" value="" placeholder="'
              . t('Search...')
              . '" /></form>';

        if  ($this->filter->isEmpty()) {
            $title = t('Filter this list');
        } else {
            $title = t('Modify this filter');
            if (! $this->filter->isEmpty()) {
                $title .= ': ' . $this->filter;
            }
        }
        return $html
            . '<a href="'
            . $this->url()->with('modifyFilter', true)
            . '" title="'
            . $title
            . '">'
            . '<i class="icon-filter"></i>'
            . '</a>';
    }

    public function render()
    {
        if (! $this->url()->getParam('modifyFilter')) {
            return $this->renderSearch() . $this->shorten($this->filter, 50);
        }
        return  $this->renderSearch()
              . '<form action="'
              . Url::fromRequest()
              . '" class="filterEditor" method="POST">'
              . '<ul class="tree"><li>'
              . $this->renderFilter($this->filter)
              . '</li></ul>'
              . '<div style="float: right">'
              . '<input type="submit" name="submit" value="Apply" />'
              . '<input type="submit" name="cancel" value="Cancel" />'
              . '</div>'
              . '</form>';
    }

    protected function shorten($string, $length)
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length) . '...';
        }
        return $string;
    }

    public function __toString()
    {
        try {
            return $this->render();
        } catch (Exception $e) {
            return 'ERROR in FilterEditor: ' . $e->getMessage();
        }
    }
}
