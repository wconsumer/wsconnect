<?php
namespace Wsconnect;

/**
 * Represents an error occurred in a user space (or re-thrown to user space from internals) with a user-friendly
 * message ready to be output
 */
class UserSpaceError extends \RuntimeException {}