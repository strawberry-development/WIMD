<?php

namespace Wimd\Renderers;

use Symfony\Component\Console\Output\OutputInterface;

interface RendererInterface
{
    /**
     * Set the output interface.
     *
     * @param OutputInterface $output
     * @return void
     */
    public function setOutput(OutputInterface $output): void;
}
