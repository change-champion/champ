<?php

declare(strict_types=1);

namespace ChangeChampion;

use ChangeChampion\Commands\AddCommand;
use ChangeChampion\Commands\CheckCommand;
use ChangeChampion\Commands\GenerateCommand;
use ChangeChampion\Commands\InitCommand;
use ChangeChampion\Commands\InstallSkillCommand;
use ChangeChampion\Commands\PreviewCommand;
use ChangeChampion\Commands\PublishCommand;
use ChangeChampion\Commands\StatusCommand;
use ChangeChampion\Commands\VersionCommand;
use Symfony\Component\Console\Application as BaseApplication;

class Application extends BaseApplication
{
    public const VERSION = '1.0.0';

    public function __construct()
    {
        parent::__construct('change-champion', self::VERSION);

        $this->addCommands([
            new InitCommand(),
            new InstallSkillCommand(),
            new AddCommand(),
            new GenerateCommand(),
            new StatusCommand(),
            new VersionCommand(),
            new PublishCommand(),
            new CheckCommand(),
            new PreviewCommand(),
        ]);
    }
}
