<?php

class ScheduledJobMetricsProcessor
{
    public function handleJob($jobId)
    {
        try {
            // Fetch keywords associated with this job
            $keywords = KeywordReportStorageManager::getKeywordsForJob($jobId);
            if (empty($keywords) || !is_array($keywords)) {
                Logger::warning(sprintf(
                    'ScheduledJobMetricsProcessor: No keywords found for job %s',
                    $jobId
                ));
                return;
            }

            // Fetch metrics for these keywords
            $metrics = AnalyticsService::fetchMetrics($keywords);
            if (empty($metrics) || !is_array($metrics)) {
                Logger::warning(sprintf(
                    'ScheduledJobMetricsProcessor: No metrics returned for job %s',
                    $jobId
                ));
                return;
            }

            $validatedMetrics = $this->validateMetrics($metrics);
            if (empty($validatedMetrics)) {
                Logger::warning(sprintf(
                    'ScheduledJobMetricsProcessor: No valid metrics after validation for job %s',
                    $jobId
                ));
                return;
            }

            // Store each metric as a separate report entry
            foreach ($validatedMetrics as $metric) {
                $reportData = array_merge(['job_id' => $jobId], $metric);
                KeywordReportStorageManager::storeKeywordReport($reportData);
            }

            // Check thresholds for notifications
            NotificationManager::checkThresholds($validatedMetrics);

            Logger::info(sprintf(
                'ScheduledJobMetricsProcessor: Successfully processed job %s',
                $jobId
            ));
        } catch (Exception $e) {
            Logger::error(sprintf(
                'ScheduledJobMetricsProcessor: Error processing job %s - %s',
                $jobId,
                $e->getMessage()
            ));
        }
    }

    /**
     * Validates the structure and types of the fetched metrics.
     *
     * @param array $metrics Raw metrics array from AnalyticsService.
     * @return array The filtered list of valid metric entries.
     */
    private function validateMetrics(array $metrics): array
    {
        $requiredKeys = ['keyword', 'date', 'clicks', 'impressions', 'ctr', 'position'];
        $validMetrics = [];

        foreach ($metrics as $index => $item) {
            if (!is_array($item)) {
                Logger::warning(sprintf(
                    'ScheduledJobMetricsProcessor: Metric at index %d is not an array and will be skipped.',
                    $index
                ));
                continue;
            }

            $missingKeys = array_diff($requiredKeys, array_keys($item));
            if (!empty($missingKeys)) {
                Logger::warning(sprintf(
                    'ScheduledJobMetricsProcessor: Metric at index %d is missing required keys: %s',
                    $index,
                    implode(', ', $missingKeys)
                ));
                continue;
            }

            if (!is_string($item['keyword'])
                || !is_string($item['date'])
                || !is_numeric($item['clicks'])
                || !is_numeric($item['impressions'])
                || !is_numeric($item['ctr'])
                || !is_numeric($item['position'])
            ) {
                Logger::warning(sprintf(
                    'ScheduledJobMetricsProcessor: Metric at index %d has invalid data types and will be skipped.',
                    $index
                ));
                continue;
            }

            $item['clicks']      = (int)   $item['clicks'];
            $item['impressions'] = (int)   $item['impressions'];
            $item['ctr']         = (float) $item['ctr'];
            $item['position']    = (float) $item['position'];

            $validMetrics[] = $item;
        }

        return $validMetrics;
    }
}