<?php

namespace app\command;

use app\model\User;
use app\model\UserPermission;
use app\util\Validate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class Setup extends Command
{
    protected static $defaultName = 'user:add';
    protected static $defaultDescription = 'Add a new user';

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $attributes = [];

        $output->writeln(PHP_EOL . '<fg=blue>[PowerPanel] 新增用户工具</>' . PHP_EOL);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        $attributes['name'] = $helper->ask($input, $output, new Question('<fg=green>输入用户名:</>' . PHP_EOL . ' > '));
        $attributes['is_admin'] = $helper->ask($input, $output, new ChoiceQuestion('<fg=green>是否为管理员:</>', ['否', '是'], 0)) == '是';
        $attributes['email'] = $helper->ask(
            $input,
            $output,
            (new Question('<fg=green>输入邮箱 [可为空]:</>' . PHP_EOL . ' > '))
                ->setValidator(fn ($value) => Validate::Data([
                    'mail' => $value
                ], [
                    'mail' => 'email'
                ])['mail'])
        );
        $attributes['password'] = $helper->ask($input, $output, new Question('<fg=green>输入密码:</>' . PHP_EOL . ' > '));

        User::HandleCreate($attributes, $attributes['password']);

        return self::SUCCESS;
    }
}
