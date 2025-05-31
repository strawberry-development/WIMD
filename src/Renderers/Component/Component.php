<?php

namespace Wimd\Renderers\Component;

use Wimd\Model\DataMetric;
use Wimd\Renderers\BaseRenderer;

class Component extends BaseRenderer
{
    protected DataMetric $metric;

    public function __construct(DataMetric $metric)
    {
        parent::__construct();
        $this->metric = $metric;
    }
}
