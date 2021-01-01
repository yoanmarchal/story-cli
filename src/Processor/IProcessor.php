<?php

namespace Story\Cli\Processor;

interface IProcessor
{
    public function compile(string $file) : string;
}
