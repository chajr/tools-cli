<?php

namespace ToolsCli\Tools\Utils;

use Symfony\Component\Console\{Input\InputInterface,
    Input\InputOption,
    Output\OutputInterface,
    Helper\FormatterHelper,
    Input\InputArgument};
use BlueRegister\{
    Register, RegisterException
};
use ToolsCli\Console\{
    Command,
    Alias,
};
use BlueConsole\Style;

class WallhavenSorterTool extends Command
{
    private const URL = '';

    /**
     * @var Register
     */
    protected $register;

    /**
     * @var Style
     */
    protected $blueStyle;

    /**
     * @var FormatterHelper
     */
    protected $formatter;

    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * @var string
     */
    protected $favoriteIds = '';

    /**
     * @param string $name
     * @param Alias $alias
     * @param Register $register
     */
    public function __construct(string $name, Alias $alias, Register $register)
    {
        $this->register = $register;
        $this->connection = new \PDO('', 'root', 'root');

        parent::__construct($name, $alias);
    }

    protected function configure() : void
    {
        $this->setName('utils:wallhaven:sorter')
            ->setDescription('')
            ->setHelp('');

        $this->addArgument(
            'path',
            InputArgument::OPTIONAL, //require with specified option
            'storm workspace main file'
        );
        
        $this->addOption(
            'favorites',
            'f',
            InputOption::VALUE_NONE,
            'Refresh list of favorites'
        );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     * @throws \InvalidArgumentException
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): void
    {
        if ($input->getOption('favorites')) {
            $this->updateFavorites();
        }
        
        //request limit (45 / 1m)
        //update favorites search: data-wallpaper-id="([a-z0-9])*"
        /**
         * main image attributes:
         * - url
         * - purity (sfw, sketchy, nsfw)
         * - dimension_x, dimension_y
         * - ratio
         * - tags {name, alias, purity}
         */
        
        //build list of tags (with their purity info, alias on separate table)
        
        
        /**
         * tables:
         * - favorites (id, wall_id)
         * - walls (id, wall_id, ...)
         * - tags (id, name, tag_id, tag_purity)
         * - aliases (id, alias)
         * - wall_tags (wall_id, tag_id)
         * - tag_alias (tag_id, alias_id)
         */
    }

    protected function updateFavorites(): void
    {
        //@todo log informations
        //@todo load ids and check if exists, if exists break scrapping
        $web = \file_get_contents(self::URL);
        \preg_match('/data-pagination="({.*})"/', $web, $paginationMatch);
        //@todo log info about favorites
        $paginationData = \json_decode(\html_entity_decode($paginationMatch[1]), true);

        $this->getIdsFromWeb($web);

        for ($i = 2; $i < $paginationData['total']; $i++) {
            $web = \file_get_contents(self::URL . "?page=$i");
            $this->getIdsFromWeb($web);
        }

        $preparedIds = \rtrim($this->favoriteIds, ', ');

        $this->connection->exec("REPLACE INTO favorites VALUES $preparedIds;");
    }

    /**
     * @param string $web
     */
    protected function getIdsFromWeb(string $web): void
    {
        preg_match_all('/data-wallpaper-id="([a-z0-9]*)"/', $web, $matches);
        //@todo log info about matches

        foreach ($matches[1] as $id) {
            $this->favoriteIds .= "('$id'), ";
        }
    }
}
