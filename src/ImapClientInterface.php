<?php declare(strict_types=1);

namespace Yai\Ymap;

use Yai\Ymap\Exceptions\ConnectionException;
use Yai\Ymap\Exceptions\ImapException;
use Yai\Ymap\Exceptions\MessageFetchException;

interface ImapClientInterface
{
    /**
     * @throws ConnectionException
     */
    public function connect(): void;

    public function disconnect(bool $expunge = false): void;

    /**
     * @return int[]
     *
     * @throws ConnectionException
     */
    public function search(string $criteria = 'ALL'): array;

    /**
     * @return int[]
     *
     * @throws ConnectionException
     */
    public function getUnreadUids(): array;

    /**
     * @throws ConnectionException
     * @throws MessageFetchException
     */
    public function fetchMessage(int $uid, ?FetchOptions $options = null): Message;

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsRead($uids): void;

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsUnread($uids): void;

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsAnswered($uids): void;

    /**
     * @param int|int[] $uids
     *
     * @throws ConnectionException
     * @throws ImapException
     */
    public function markAsUnanswered($uids): void;
}
