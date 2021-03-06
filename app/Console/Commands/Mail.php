<?php

namespace App\Console\Commands;

use App\Models\ApiAuthorization;
use App\Models\User;
use App\Util\SendMail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail as MailFacade;
use InvalidArgumentException;

final class Mail extends Command
{
    use SendMail;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mail {content} {users} {--confirm}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send a given e-mail content to given users.';

    /**
     * @var array<string, string>|null
     */
    private ?array $passwords;

    public function handle(): int
    {
        $users = array_map('intval', explode(',', (string) $this->input->getArgument('users')));
        $content = (string) $this->input->getArgument('content');
        $this->withProgressBar(
            $users,
            fn (int $userId) => $this->sendMailContent($content, $userId),
        );
        $this->getOutput()->writeln('');

        return 0;
    }

    private function sendMailContent(string $content, int $userId): void
    {
        $method = 'send' . ucfirst($content) . 'Mail';

        if (!method_exists($this, $method)) {
            throw new InvalidArgumentException("Unknown content $content");
        }

        $this->$method(User::findOrFail($userId));
    }
}
