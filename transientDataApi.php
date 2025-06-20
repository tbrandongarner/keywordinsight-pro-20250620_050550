const CACHE_KEY_PREFIX = 'kipro_';

    /**
     * Maximum length of a transient key in WordPress.
     */
    const MAX_KEY_LENGTH = 172;

    /**
     * Length of the hash appended to truncated keys to avoid collisions.
     */
    const HASH_LENGTH = 8;

    /**
     * Retrieve a value from the cache.
     *
     * Returns null if the key is invalid or no data is stored under that key.
     * Returns false when a boolean false was explicitly stored.
     *
     * @param string $key Cache key.
     * @return mixed|null|false Retrieved value, false if stored false, null if missing or invalid key.
     */
    public static function get( string $key ) {
        $key = self::sanitize_key( $key );
        if ( '' === $key ) {
            return null;
        }

        $value = get_transient( $key );

        // Distinguish between "no data" and "stored false".
        if ( false === $value ) {
            $exists = get_option( '_transient_' . $key );
            if ( false === $exists ) {
                // Missing transient.
                /**
                 * Filter when a transient is missing or key invalid.
                 *
                 * @param null   $value Always null here.
                 * @param string $key   Cache key.
                 */
                return apply_filters( 'kipro_transient_get_missing', null, $key );
            }
            // Else: stored false; fall through to filter below with $value === false.
        }

        /**
         * Filter the returned transient value.
         *
         * @param mixed  $value Retrieved value or false.
         * @param string $key   Cache key.
         */
        return apply_filters( 'kipro_transient_get', $value, $key );
    }

    /**
     * Store a value in the cache.
     *
     * Returns null on invalid key, otherwise the result of set_transient.
     *
     * @param string $key   Cache key.
     * @param mixed  $value Value to store.
     * @param int    $ttl   Time to live in seconds. 0 for no expiration.
     * @return bool|null True on success, false on failure, null if key invalid.
     */
    public static function set( string $key, $value, int $ttl = 0 ) {
        $key = self::sanitize_key( $key );
        if ( '' === $key ) {
            return null;
        }

        $success = set_transient( $key, $value, $ttl );

        /**
         * Filter the result of setting a transient.
         *
         * @param bool   $success Success flag.
         * @param string $key     Cache key.
         * @param mixed  $value   Stored value.
         * @param int    $ttl     Time to live.
         */
        return apply_filters( 'kipro_transient_set', $success, $key, $value, $ttl );
    }

    /**
     * Delete a cached value.
     *
     * Returns null on invalid key, otherwise the result of delete_transient.
     *
     * @param string $key Cache key.
     * @return bool|null True on success, false on failure, null if key invalid.
     */
    public static function delete( string $key ) {
        $key = self::sanitize_key( $key );
        if ( '' === $key ) {
            return null;
        }

        $success = delete_transient( $key );

        /**
         * Filter the result of deleting a transient.
         *
         * @param bool   $success Success flag.
         * @param string $key     Cache key.
         */
        return apply_filters( 'kipro_transient_delete', $success, $key );
    }

    /**
     * Sanitize and prefix the cache key, enforcing length limits
     * and appending a hash to truncated keys.
     *
     * @param string $key Raw key.
     * @return string Sanitized, prefixed, and length-limited key.
     */
    private static function sanitize_key( string $key ): string {
        // Basic sanitize: lowercase, alphanumeric, dashes, underscores.
        $key = sanitize_key( $key );
        if ( '' === $key ) {
            return '';
        }

        // Ensure prefix.
        if ( 0 !== strpos( $key, self::CACHE_KEY_PREFIX ) ) {
            $key = self::CACHE_KEY_PREFIX . $key;
        }

        // Enforce max length.
        if ( strlen( $key ) > self::MAX_KEY_LENGTH ) {
            // Compute how much of the human-readable part we can keep.
            $hash = substr( md5( $key ), 0, self::HASH_LENGTH );
            $max_human = self::MAX_KEY_LENGTH - self::HASH_LENGTH - 1; // 1 for underscore
            $human   = substr( $key, 0, $max_human );
            $key     = $human . '_' . $hash;
        }

        return $key;
    }
}