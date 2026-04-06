<?php
if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_SearchVector
{
    private $moduleDir;
    private $vectorDir;
    private $dataDir;
    private $statsDir;
    private $logDir;
    private $memoriesFile;
    private $searchStatsFile;

    public function __construct()
    {
        $this->moduleDir = dirname(__DIR__, 2);
        $this->vectorDir = $this->moduleDir . '/data/vector';
        $this->dataDir = $this->moduleDir . '/data';
        $this->statsDir = $this->dataDir . '/stats';
        $this->logDir = $this->moduleDir . '/logs';
        $this->memoriesFile = $this->vectorDir . '/memories.json';
        $this->searchStatsFile = $this->statsDir . '/search_stats.json';
        
        $this->ensureDirectories();
        $this->handleRequest();
    }

    private function ensureDirectories()
    {
        $dirs = [$this->vectorDir, $this->statsDir, $this->logDir];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }

    private function handleRequest()
    {
        header('Content-Type: application/json');
        
        $query = isset($_GET['q']) ? trim($_GET['q']) : '';
        $limit = isset($_GET['limit']) ? intval($_GET['limit']) : 10;
        $category = isset($_GET['category']) ? $this->sanitizeCategory($_GET['category']) : null;
        $minSimilarity = isset($_GET['min_similarity']) ? floatval($_GET['min_similarity']) : 0;
        $includeMetadata = isset($_GET['include_metadata']) ? filter_var($_GET['include_metadata'], FILTER_VALIDATE_BOOLEAN) : false;
        
        if (empty($query)) {
            $this->jsonResponse(false, 'Arama terimi girin');
        }
        
        if ($limit > 100) {
            $limit = 100;
        }
        
        $searchStart = microtime(true);
        $results = $this->vectorSearch($query, $limit, $category);
        $searchTime = round((microtime(true) - $searchStart) * 1000, 2);
        
        if ($minSimilarity > 0 && !empty($results)) {
            $results = array_filter($results, function($item) use ($minSimilarity) {
                return $item['similarity'] >= $minSimilarity;
            });
            $results = array_values($results);
        }
        
        $formattedResults = $this->formatResults($results, $includeMetadata);
        $this->updateSearchStats($query, count($formattedResults));
        $this->logSearch($query, count($formattedResults));
        
        $this->jsonResponse(true, 'Arama tamamlandı', [
            'results' => $formattedResults,
            'total_found' => count($formattedResults),
            'query' => $query,
            'limit' => $limit,
            'category' => $category,
            'min_similarity' => $minSimilarity,
            'search_time_ms' => $searchTime
        ]);
    }

    private function vectorSearch($query, $limit, $category)
    {
        $results = [];
        
        if (!file_exists($this->memoriesFile)) {
            return $this->fallbackTextSearch($query, $limit, $category);
        }
        
        $content = file_get_contents($this->memoriesFile);
        $memories = json_decode($content, true);
        
        if (!is_array($memories)) {
            return $this->fallbackTextSearch($query, $limit, $category);
        }
        
        $queryWords = array_unique(array_filter(explode(' ', strtolower($query))));
        $queryWords = array_map('trim', $queryWords);
        
        foreach ($memories as $memory) {
            if ($category && isset($memory['category']) && $memory['category'] !== $category) {
                continue;
            }
            
            $contentText = isset($memory['content']) ? strtolower($memory['content']) : '';
            $contentText .= ' ' . (isset($memory['text']) ? strtolower($memory['text']) : '');
            
            $matchedWords = 0;
            $score = 0;
            
            foreach ($queryWords as $word) {
                if (strpos($contentText, $word) !== false) {
                    $matchedWords++;
                    $score += 10;
                }
            }
            
            if ($matchedWords > 0) {
                $wordMatchRatio = $matchedWords / count($queryWords);
                $score += $wordMatchRatio * 20;
            }
            
            $score = min(100, $score);
            
            if ($score > 0) {
                $results[] = [
                    'id' => isset($memory['id']) ? $memory['id'] : md5(serialize($memory)),
                    'content' => isset($memory['content']) ? $memory['content'] : (isset($memory['text']) ? $memory['text'] : ''),
                    'similarity' => $score / 100,
                    'category' => isset($memory['category']) ? $memory['category'] : 'general',
                    'created_at' => isset($memory['created_at']) ? $memory['created_at'] : null,
                    'metadata' => isset($memory['metadata']) ? $memory['metadata'] : []
                ];
            }
        }
        
        usort($results, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
        
        return array_slice($results, 0, $limit);
    }

    private function fallbackTextSearch($query, $limit, $category)
    {
        $results = [];
        
        if (!file_exists($this->memoriesFile)) {
            return $results;
        }
        
        $content = file_get_contents($this->memoriesFile);
        $memories = json_decode($content, true);
        
        if (!is_array($memories)) {
            return $results;
        }
        
        $queryWords = array_unique(array_filter(explode(' ', strtolower($query))));
        $queryWords = array_map('trim', $queryWords);
        
        foreach ($memories as $memory) {
            if ($category && isset($memory['category']) && $memory['category'] !== $category) {
                continue;
            }
            
            $contentText = isset($memory['content']) ? strtolower($memory['content']) : '';
            $contentText .= ' ' . (isset($memory['text']) ? strtolower($memory['text']) : '');
            
            $matchedWords = 0;
            $score = 0;
            
            foreach ($queryWords as $word) {
                if (strpos($contentText, $word) !== false) {
                    $matchedWords++;
                    $score += 10;
                }
            }
            
            if ($matchedWords > 0) {
                $wordMatchRatio = $matchedWords / count($queryWords);
                $score += $wordMatchRatio * 20;
            }
            
            $score = min(100, $score);
            
            if ($score > 0) {
                $results[] = [
                    'id' => isset($memory['id']) ? $memory['id'] : md5(serialize($memory)),
                    'content' => isset($memory['content']) ? $memory['content'] : (isset($memory['text']) ? $memory['text'] : ''),
                    'similarity' => $score / 100,
                    'category' => isset($memory['category']) ? $memory['category'] : 'general',
                    'created_at' => isset($memory['created_at']) ? $memory['created_at'] : null,
                    'metadata' => isset($memory['metadata']) ? $memory['metadata'] : []
                ];
            }
        }
        
        usort($results, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
        
        return array_slice($results, 0, $limit);
    }

    private function formatResults($results, $includeMetadata)
    {
        $formatted = [];
        
        if (empty($results)) {
            return $formatted;
        }
        
        foreach ($results as $result) {
            $item = [
                'id' => $result['id'],
                'content' => $this->truncateContent($result['content']),
                'similarity' => round($result['similarity'] * 100, 2),
                'category' => $result['category'],
                'created_at' => $result['created_at']
            ];
            
            if ($includeMetadata && isset($result['metadata'])) {
                $item['metadata'] = $result['metadata'];
            }
            
            $formatted[] = $item;
        }
        
        usort($formatted, function($a, $b) {
            return $b['similarity'] - $a['similarity'];
        });
        
        return $formatted;
    }

    private function truncateContent($content, $maxLength = 300)
    {
        if (strlen($content) <= $maxLength) {
            return $content;
        }
        
        $truncated = substr($content, 0, $maxLength);
        $lastSpace = strrpos($truncated, ' ');
        
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated . '...';
    }

    private function updateSearchStats($query, $resultCount)
    {
        $stats = [];
        
        if (file_exists($this->searchStatsFile)) {
            $content = file_get_contents($this->searchStatsFile);
            $stats = json_decode($content, true);
            if (!is_array($stats)) {
                $stats = [];
            }
        }
        
        $today = date('Y-m-d');
        
        if (!isset($stats['daily'][$today])) {
            $stats['daily'][$today] = [
                'total_searches' => 0,
                'total_results' => 0,
                'unique_queries' => []
            ];
        }
        
        $stats['daily'][$today]['total_searches']++;
        $stats['daily'][$today]['total_results'] += $resultCount;
        
        $queryHash = md5(strtolower(trim($query)));
        if (!in_array($queryHash, $stats['daily'][$today]['unique_queries'])) {
            $stats['daily'][$today]['unique_queries'][] = $queryHash;
        }
        
        if (!isset($stats['total'])) {
            $stats['total'] = [
                'searches' => 0,
                'most_searched' => []
            ];
        }
        
        $stats['total']['searches']++;
        
        $queryNormalized = strtolower(trim($query));
        if (!isset($stats['total']['most_searched'][$queryNormalized])) {
            $stats['total']['most_searched'][$queryNormalized] = 0;
        }
        $stats['total']['most_searched'][$queryNormalized]++;
        
        arsort($stats['total']['most_searched']);
        $stats['total']['most_searched'] = array_slice($stats['total']['most_searched'], 0, 50);
        
        $stats['daily'] = array_slice($stats['daily'], -30, null, true);
        
        file_put_contents($this->searchStatsFile, json_encode($stats, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function logSearch($query, $resultCount)
    {
        $logFile = $this->logDir . '/searches.log';
        $logEntry = '[' . date('Y-m-d H:i:s') . "] Query: {$query} | Results: {$resultCount} | IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . "\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function sanitizeCategory($category)
    {
        return preg_replace('/[^a-zA-Z0-9_-]/', '', strtolower(trim($category)));
    }

    private function jsonResponse($success, $message, $data = [])
    {
        $response = [
            'success' => $success,
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        if (!empty($data)) {
            $response['data'] = $data;
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}

new APIMaster_SearchVector();
?>