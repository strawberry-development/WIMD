<?php

namespace Wimd\Contracts;

use Symfony\Component\Console\Output\OutputInterface;

interface WimdSeederInterface
{
    /**
     * Set the output interface.
     *
     * @param OutputInterface $output
     * @return $this
     */
    public function setOutput(OutputInterface $output);

    /**
     * Get the current seeding mode
     *
     * @return string
     */
    public function getMode(): string;

    /**
     * Set the seeding mode
     *
     * @param string $mode 'light' or 'full'
     * @return $this
     */
    public function setMode(string $mode): self;
}
