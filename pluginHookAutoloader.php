public static function registerHooks(string $hooksDir = null): void
    {
        $hooksDir = $hooksDir ?? __DIR__ . '/Hooks';
        if (! is_dir($hooksDir)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($hooksDir, FilesystemIterator::SKIP_DOTS)
        );

        $phpFiles = [];
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php' || $file->isLink()) {
                continue;
            }
            $phpFiles[] = $file->getPathname();
        }

        if (empty($phpFiles)) {
            return;
        }

        $declaredBefore = get_declared_classes();
        foreach ($phpFiles as $filePath) {
            require_once $filePath;
        }
        $declaredAfter = get_declared_classes();

        $newClasses = array_diff($declaredAfter, $declaredBefore);
        foreach ($newClasses as $class) {
            try {
                $ref = new ReflectionClass($class);
                if (! $ref->isInstantiable()) {
                    continue;
                }
                if ($ref->implementsInterface(HookInterface::class)) {
                    /** @var HookInterface $instance */
                    $instance = $ref->newInstance();
                    $instance->register();
                }
            } catch (Throwable $e) {
                // Skip any class that fails reflection or instantiation
                continue;
            }
        }
    }
}