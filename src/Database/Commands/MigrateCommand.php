<?php

namespace Xycc\Winter\Database\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Xycc\Winter\Command\Attributes\AsCommand;

/**
 * @example ```shell
 *          php winter migrate --match=create*table 迁移匹配名字的文件
 *          php winter migrate rollback [-n number] [--all] 回滚迁移, 默认回滚一次 -n = 1
 *          php winter migrate reset 重置数据库, 全部删除然后重新执行迁移
 *          php winter migrate 迁移未执行的迁移文件
 *          php winter migrate create file_name 创建迁移文件
 * ```
 */
#[AsCommand('migrate', aliases: ['m'])]
class MigrateCommand extends Command
{
    protected function configure()
    {
        $this->addArgument('file', InputArgument::IS_ARRAY, 'files to be migrated', []);
    }
}