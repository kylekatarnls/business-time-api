<?php

namespace Tests\Unit\Model;

use App\Models\ApiAuthorization;
use App\Models\ApiAuthorizationQuotaNotification;
use App\Models\Plan;
use App\Models\SubscriptionQuotaNotification;
use App\Models\User;
use Exception;
use Generator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use IteratorAggregate;
use Laravel\Cashier\Exceptions\InvalidCustomer;
use Laravel\Cashier\Subscription;
use ReflectionProperty;
use Stripe\Collection;
use Tests\TestCase;

final class UserTest extends TestCase
{
    public function testGetLimit(): void
    {
        $factor = $this->app['config']->get('app.quota_factor');
        $ziggy = $this->newZiggy();

        $this->app['config']->set('app.quota_factor', []);

        $this->assertSame(0, $ziggy->getLimit());
        $this->assertSame(0, $ziggy->getLimit(null));
        $this->assertSame(200, $ziggy->getLimit(200));

        $this->app['config']->set('app.quota_factor', [
            $ziggy->id => 3,
        ]);

        $this->assertSame(600, $ziggy->getLimit(200));
        $this->assertSame(600_000, $ziggy->getLimit('pro'));
        $this->assertSame(INF, $ziggy->getLimit('premium'));

        $this->app['config']->set('app.quota_factor', $factor);

        $this->assertSame(20_000, $ziggy->getLimit(Plan::fromId('start')));
        $this->assertSame(5_000, $ziggy->getLimit(['start', 'pro']));
    }

    public function testAddAndSubBalance(): void
    {
        $user = $this->newZiggy();
        $user->createAsStripeCustomer();

        $this->assertSame(0.0, $user->getBalance());

        $user->addBalance(1.5);
        $user->subBalance(4);
    }

    public function testAddBalanceException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Negative balance cannot be added, use subBalance() instead.');

        $user = new User();
        $user->addBalance(-0.3);
    }

    public function testSubBalanceException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Negative balance cannot be subtracted, use addBalance() instead.');

        $user = new User();
        $user->subBalance(-553);
    }

    public function testGetAuthorizations(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->apiAuthorizations()->create([
            'name' => 'Music',
            'type' => 'domain',
            'value' => 'music.github.io',
        ]);

        $authorizations = $ziggy->getAuthorizations();

        $this->assertSame([ApiAuthorization::class], array_map('get_class', iterator_to_array($authorizations)));
        $this->assertSame('music.github.io', $authorizations[0]->value);
    }

    public function testCancelSubscriptionsSilently(): void
    {
        $exception = new Exception('stop');
        $ziggy = $this->newZiggy();
        $good = new class ($exception) extends Subscription {
            public bool $cancelled = false;

            public function getTable()
            {
                return 'subscriptions';
            }

            public function __construct(private Exception $exception)
            {
                parent::__construct([
                    'name' => 'start',
                    'stripe_id' => 1,
                    'stripe_status' => 'active',
                ]);
            }

            public function cancelNow()
            {
                $this->cancelled = true;
            }
        };
        $bad = new class ($exception) extends Subscription {
            public bool $cancelled = false;

            public function getTable()
            {
                return 'subscriptions';
            }

            public function __construct(private Exception $exception)
            {
                parent::__construct([
                    'name' => 'pro',
                    'stripe_id' => 2,
                    'stripe_status' => 'active',
                ]);
            }

            public function cancelNow()
            {
                throw $this->exception;
            }
        };
        $collection = new class ([$good, $bad]) implements IteratorAggregate {
            public function __construct(private array $items)
            {
            }

            public function getIterator(): Generator
            {
                foreach ($this->items as $key => $item) {
                    yield $key => $item;
                }
            }

            public function __call(string $name, array $arguments)
            {
                return $this;
            }
        };
        $ziggy->subscriptions = $collection;
        $subscriptions = $ziggy->subscriptions();
        $subscriptions->save($good);
        $subscriptions->save($bad);

        Log::shouldReceive('notice')->with($exception);

        $ziggy->cancelSubscriptionsSilently();

        $subscriptions = $ziggy->getSubscriptions();

        $this->assertSame(
            [true, true],
            array_map(static fn ($s) => $s instanceof Subscription, iterator_to_array($subscriptions)),
        );
        $this->assertSame(
            [true, false],
            array_map(static fn ($s) => $s->cancelled, iterator_to_array($subscriptions)),
        );
    }

    public function testGetCustomerSubscriptionsException(): void
    {
        $this->expectException(InvalidCustomer::class);
        $this->expectExceptionMessage('User is not a Stripe customer yet. See the createAsStripeCustomer method.');

        $ziggy = $this->newZiggy();
        $ziggy->getCustomerSubscriptions();
        $this->assertTrue($ziggy->hasStripeId());
    }

    public function testGetCustomerSubscriptions(): void
    {
        $ziggy = $this->newZiggy();
        $ziggy->createAsStripeCustomer();
        $subscriptions = $ziggy->getCustomerSubscriptions();
        $this->assertInstanceOf(Collection::class, $subscriptions);
        $this->assertSame([], iterator_to_array($subscriptions));
        $this->assertTrue($ziggy->hasStripeId());
    }
}
