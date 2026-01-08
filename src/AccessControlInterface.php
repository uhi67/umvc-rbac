<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\rbac;

/**
 * @property-read AccessItemInterface[] $items -- the list of all access items
 */
interface AccessControlInterface
{
    /**
     * @param string|null $uid -- uid of the user (may or may not be in the user table)
     * @param string $permission -- The name of the role/permission to check
     * @param array $context -- The context if the permission needs a context
     * @return bool -- The user has this permission
     */
    public function can(?string $uid, string $permission, array $context = []): bool;

    /**
     * @return AccessItemInterface[]
     */
    public function getItems(): array;

    public function getItem(string $name): ?AccessItemInterface;

    public function assign(string $uid, string $itemName, ?string $creator = null): bool;

    public function revoke(string $uid, string $itemName): bool;

    /**
     * Must return the assignable item names.
     * (If no item type is implemented, may return all item names)
     *
     * @return string[]
     */
    public function getRoleNames(): array;
}
