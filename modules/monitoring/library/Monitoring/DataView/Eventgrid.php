<?php
// {{{ICINGA_LICENSE_HEADER}}}
// {{{ICINGA_LICENSE_HEADER}}}

namespace Icinga\Module\Monitoring\DataView;

class Eventgrid extends DataView
{
    /**
     * Retrieve columns provided by this view
     *
     * @return array
     */
    public function getColumns()
    {
        return array(
            'day',
            'cnt_events',
            'cnt_up',
            'cnt_down_hard',
            'cnt_down',
            'cnt_unreachable_hard',
            'cnt_unreachable',
            'cnt_unknown_hard',
            'cnt_unknown',
            'cnt_unknown_hard',
            'cnt_critical',
            'cnt_critical_hard',
            'cnt_warning',
            'cnt_warning_hard',
            'cnt_ok',
        );
    }

    public function getSortRules()
    {
        return array(
            'day' => array(
                'order' => self::SORT_DESC
            )
        );
    }
}
