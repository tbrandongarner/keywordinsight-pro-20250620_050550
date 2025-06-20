* @var \OpenAIClient
     */
    private $client;

    public function __construct() {
        $this->client = AIService::getClient();
    }

    /**
     * Suggest relevant long-tail keywords based on a seed keyword.
     *
     * @param string $seed
     * @return array
     */
    public function suggestKeywords(string $seed): array {
        $seed = sanitize_text_field($seed);
        if ('' === trim($seed)) {
            return [];
        }

        $transient_key = 'kio_suggest_keywords_' . md5($seed);
        $cached = get_transient($transient_key);
        if (is_array($cached)) {
            return $cached;
        }

        $prompt = sprintf(
            'Generate a list of 10 relevant long-tail keywords based on the seed keyword: "%s". Return the result as a JSON array of strings.',
            $seed
        );

        try {
            $response = $this->client->request([
                'model'       => 'gpt-3.5-turbo',
                'messages'    => [
                    ['role' => 'system', 'content' => 'You are an expert SEO assistant.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'max_tokens'  => 150,
                'temperature' => 0.7,
            ]);

            $content  = trim($response['choices'][0]['message']['content'] ?? '');
            $keywords = json_decode($content, true);

            if (JSON_ERROR_NONE !== json_last_error() || !is_array($keywords)) {
                error_log('KeywordOutlineGenerator::suggestKeywords JSON decode error: ' . json_last_error_msg() . ' Response: ' . $content);
                $keywords = [];
            }

            $keywords = array_map('sanitize_text_field', $keywords);
            set_transient($transient_key, $keywords, 12 * HOUR_IN_SECONDS);

            return $keywords;
        } catch (\Exception $e) {
            error_log('KeywordOutlineGenerator::suggestKeywords error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate a detailed article outline based on keywords.
     *
     * @param array $keywords
     * @return array
     */
    public function generateOutline(array $keywords): array {
        $keywords = array_filter(array_map('sanitize_text_field', $keywords));
        if (empty($keywords)) {
            return [];
        }

        $serialized      = wp_json_encode($keywords);
        $transient_key   = 'kio_generate_outline_' . md5($serialized);
        $cached_outline  = get_transient($transient_key);
        if (is_array($cached_outline)) {
            return $cached_outline;
        }

        $prompt = sprintf(
            'Create a detailed article outline for the following keywords: %s. Structure the outline with H2 headings as keys and an array of H3 subheadings as values. Return as a JSON object.',
            $serialized
        );

        try {
            $response = $this->client->request([
                'model'       => 'gpt-3.5-turbo',
                'messages'    => [
                    ['role' => 'system', 'content' => 'You are an expert content strategist.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'max_tokens'  => 500,
                'temperature' => 0.7,
            ]);

            $content = trim($response['choices'][0]['message']['content'] ?? '');
            $outline = json_decode($content, true);

            if (JSON_ERROR_NONE !== json_last_error() || !is_array($outline)) {
                error_log('KeywordOutlineGenerator::generateOutline JSON decode error: ' . json_last_error_msg() . ' Response: ' . $content);
                $outline = [];
            }

            set_transient($transient_key, $outline, 12 * HOUR_IN_SECONDS);

            return $outline;
        } catch (\Exception $e) {
            error_log('KeywordOutlineGenerator::generateOutline error: ' . $e->getMessage());
            return [];
        }
    }
}