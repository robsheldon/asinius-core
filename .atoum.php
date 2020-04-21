<?php

use mageekguy\atoum;

$report = new atoum\reports\realtime\cli();
$report->addWriter(new atoum\writers\std\out());
$runner->addReport($report);
$script->enableBranchAndPathCoverage();
