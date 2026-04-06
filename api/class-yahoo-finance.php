<?php
/**
 * API Master Module - Yahoo Finance API
 * Hisse senedi, döviz, kripto para ve piyasa verileri sağlayıcısı
 * Masal Panel uyumlu - WordPress bağımsız
 * 
 * @package     APIMaster
 * @subpackage  API
 * @version     1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class APIMaster_API_YahooFinance implements APIMaster_APIInterface {
    
    /**
     * API Endpoints
     * @var array
     */
    private $endpoints = [
        'quote' => 'https://query1.finance.yahoo.com/v7/finance/quote',
        'chart' => 'https://query1.finance.yahoo.com/v8/finance/chart',
        'search' => 'https://query1.finance.yahoo.com/v1/finance/search',
        'marketsummary' => 'https://query1.finance.yahoo.com/v6/finance/quote/marketSummary',
        'trending' => 'https://query1.finance.yahoo.com/v1/finance/trending/US'
    ];
    
    /**
     * API Key (not required for Yahoo Finance)
     * @var string|null
     */
    private $apiKey = null;
    
    /**
     * Current model (for interface compatibility)
     * @var string|null
     */
    private $model = null;
    
    /**
     * User Agent
     * @var string
     */
    private $userAgent = 'Mozilla/5.0 (compatible; APIMaster/1.0)';
    
    /**
     * Timeout in seconds
     * @var int
     */
    private $timeout = 30;
    
    /**
     * Constructor
     * 
     * @param array $config Configuration array
     */
    public function __construct(array $config = []) {
        if (isset($config['api_key'])) {
            $this->setApiKey($config['api_key']);
        }
        
        if (isset($config['user_agent'])) {
            $this->userAgent = $config['user_agent'];
        }
        
        if (isset($config['timeout'])) {
            $this->timeout = (int) $config['timeout'];
        }
    }
    
    /**
     * Set API Key (Yahoo Finance doesn't require API key)
     * 
     * @param string $apiKey
     * @return self
     */
    public function setApiKey($apiKey) {
        $this->apiKey = $apiKey;
        return $this;
    }
    
    /**
     * Set Model (for interface compatibility)
     * 
     * @param string $model
     * @return self
     */
    public function setModel($model) {
        $this->model = $model;
        return $this;
    }
    
    /**
     * Get Current Model
     * 
     * @return string|null
     */
    public function getModel() {
        return $this->model;
    }
    
    /**
     * Complete method - Search stocks
     * 
     * @param string $prompt Search query
     * @param array $options Options
     * @return array Search results
     */
    public function complete($prompt, $options = []) {
        return $this->search($prompt, $options);
    }
    
    /**
     * Stream method (not supported)
     * 
     * @param string $prompt Search query
     * @param callable $callback Callback function
     * @param array $options Options
     * @return void
     */
    public function stream($prompt, $callback, $options = []) {
        $result = $this->complete($prompt, $options);
        if (is_callable($callback)) {
            $callback(json_encode($result));
        }
    }
    
    /**
     * Get Available Models
     * 
     * @return array
     */
    public function getModels() {
        return [
            'quote' => 'Stock/Crypto/Forex Quote',
            'historical' => 'Historical Data',
            'search' => 'Search Stocks',
            'market_summary' => 'Market Summary',
            'trending' => 'Trending Stocks'
        ];
    }
    
    /**
     * Get Provider Capabilities
     * 
     * @return array
     */
    public function getCapabilities() {
        return [
            'streaming' => false,
            'chat' => false,
            'completion' => true,
            'models' => true,
            'max_tokens' => null,
            'functions' => false,
            'vision' => false,
            'supported_features' => [
                'stock_quotes',
                'crypto_prices',
                'forex_rates',
                'historical_data',
                'market_summary',
                'trending_stocks'
            ]
        ];
    }
    
    /**
     * Check API Health
     * 
     * @return array
     */
    public function checkHealth() {
        $result = $this->getQuote('AAPL');
        
        if (isset($result['error'])) {
            return ['status' => 'error', 'message' => $result['error']];
        }
        
        if (isset($result['success']) && $result['success'] === true) {
            return ['status' => 'healthy', 'message' => 'API is working'];
        }
        
        return ['status' => 'error', 'message' => 'API returned unexpected response'];
    }
    
    /**
     * Chat method (not supported)
     * 
     * @param array $messages Messages array
     * @param array $options Options
     * @param callable|null $callback Callback for streaming
     * @return array
     */
    public function chat($messages, $options = [], $callback = null) {
        return [
            'error' => 'Chat method is not supported by Yahoo Finance API',
            'supported_methods' => ['complete', 'getQuote', 'getHistoricalData', 'search']
        ];
    }
    
    /**
     * Extract Text from Response
     * 
     * @param array $response Quote response
     * @return string
     */
    public function extractText($response) {
        if (isset($response['error'])) {
            return $response['error'];
        }
        
        if (isset($response['quote'])) {
            $q = $response['quote'];
            return sprintf(
                "%s (%s): %.2f %s (%.2f%%)",
                $q['short_name'],
                $q['symbol'],
                $q['regular_market_price'] ?? 0,
                $q['currency'] ?? '',
                $q['regular_market_change_percent'] ?? 0
            );
        }
        
        if (isset($response['quotes'][0])) {
            $q = $response['quotes'][0];
            return sprintf(
                "%s (%s): %.2f %s",
                $q['short_name'],
                $q['symbol'],
                $q['regular_market_price'] ?? 0,
                $q['currency'] ?? ''
            );
        }
        
        return json_encode($response);
    }
    
    /**
     * Get Quote for Symbol(s)
     * 
     * @param string|array $symbols Symbol or array of symbols
     * @return array Quote data
     */
    public function getQuote($symbols) {
        $isMultiple = is_array($symbols);
        $symbolsStr = $isMultiple ? implode(',', $symbols) : $symbols;
        
        $params = ['symbols' => $symbolsStr];
        $result = $this->makeRequest('quote', $params);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if (isset($result['quoteResponse']['result'])) {
            $quotes = [];
            foreach ($result['quoteResponse']['result'] as $quote) {
                $quotes[] = $this->parseQuote($quote);
            }
            
            $response = ['success' => true, 'quotes' => $quotes];
            if (!$isMultiple && count($quotes) === 1) {
                $response['quote'] = $quotes[0];
            }
            return $response;
        }
        
        return ['error' => 'No data found for symbol: ' . $symbolsStr];
    }
    
    /**
     * Get Historical Data
     * 
     * @param string $symbol Symbol
     * @param array $options Options (interval, range, include_pre_post)
     * @return array Historical data
     */
    public function getHistoricalData($symbol, $options = []) {
        $interval = $options['interval'] ?? '1d';
        $range = $options['range'] ?? '1mo';
        $includePrePost = $options['include_pre_post'] ?? false;
        
        $params = [
            'symbol' => $symbol,
            'interval' => $interval,
            'range' => $range,
            'includePrePost' => $includePrePost ? 'true' : 'false'
        ];
        
        $result = $this->makeRequest('chart', $params);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if (isset($result['chart']['result'][0])) {
            $chart = $result['chart']['result'][0];
            $timestamps = $chart['timestamp'] ?? [];
            $indicators = $chart['indicators']['quote'][0];
            $adjclose = $chart['indicators']['adjclose'][0]['adjclose'] ?? [];
            
            $history = [];
            foreach ($timestamps as $index => $timestamp) {
                $history[] = [
                    'date' => date('Y-m-d H:i:s', $timestamp),
                    'timestamp' => $timestamp,
                    'open' => $indicators['open'][$index] ?? null,
                    'high' => $indicators['high'][$index] ?? null,
                    'low' => $indicators['low'][$index] ?? null,
                    'close' => $indicators['close'][$index] ?? null,
                    'volume' => $indicators['volume'][$index] ?? null,
                    'adjclose' => $adjclose[$index] ?? null
                ];
            }
            
            $prices = array_filter(array_column($history, 'close'), function($v) { return $v !== null; });
            
            return [
                'success' => true,
                'symbol' => $chart['meta']['symbol'],
                'currency' => $chart['meta']['currency'],
                'exchange' => $chart['meta']['exchangeName'],
                'timezone' => $chart['meta']['timezone'],
                'history' => $history,
                'stats' => !empty($prices) ? [
                    'high' => max($prices),
                    'low' => min($prices),
                    'open' => $history[0]['open'] ?? null,
                    'close' => end($history)['close'] ?? null,
                    'change' => (end($history)['close'] ?? 0) - ($history[0]['open'] ?? 0),
                    'change_percent' => ($history[0]['open'] ?? 0) != 0 ? 
                        ((end($history)['close'] - $history[0]['open']) / $history[0]['open']) * 100 : null,
                    'avg_volume' => array_sum(array_column($history, 'volume')) / count($history)
                ] : []
            ];
        }
        
        return ['error' => 'No historical data found for symbol: ' . $symbol];
    }
    
    /**
     * Search Stocks
     * 
     * @param string $query Search query
     * @param array $options Options
     * @return array Search results
     */
    public function search($query, $options = []) {
        $quotesCount = $options['quotes_count'] ?? 10;
        $newsCount = $options['news_count'] ?? 0;
        $enableFuzzy = $options['enable_fuzzy'] ?? true;
        
        $params = [
            'q' => $query,
            'quotesCount' => $quotesCount,
            'newsCount' => $newsCount,
            'enableFuzzyQuery' => $enableFuzzy ? 'true' : 'false'
        ];
        
        $result = $this->makeRequest('search', $params);
        
        if (isset($result['error'])) {
            return $result;
        }
        
        $response = ['success' => true];
        
        if (isset($result['quotes'])) {
            $quotes = [];
            foreach ($result['quotes'] as $quote) {
                $quotes[] = [
                    'symbol' => $quote['symbol'],
                    'short_name' => $quote['shortname'] ?? $quote['longname'] ?? $quote['symbol'],
                    'long_name' => $quote['longname'] ?? null,
                    'quote_type' => $quote['quoteType'] ?? null,
                    'exchange' => $quote['exchange'] ?? null,
                    'sector' => $quote['sector'] ?? null,
                    'industry' => $quote['industry'] ?? null
                ];
            }
            $response['quotes'] = $quotes;
        }
        
        if (isset($result['news']) && $newsCount > 0) {
            $news = [];
            foreach ($result['news'] as $item) {
                $news[] = [
                    'title' => $item['title'],
                    'link' => $item['link'],
                    'publisher' => $item['publisher'],
                    'published_at' => isset($item['providerPublishTime']) ? 
                        date('Y-m-d H:i:s', $item['providerPublishTime']) : null
                ];
            }
            $response['news'] = $news;
        }
        
        return $response;
    }
    
    /**
     * Get Market Summary
     * 
     * @return array Market summary
     */
    public function getMarketSummary() {
        $result = $this->makeRequest('marketsummary');
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if (isset($result['marketSummaryResponse']['result'])) {
            $markets = [];
            foreach ($result['marketSummaryResponse']['result'] as $market) {
                $markets[] = [
                    'symbol' => $market['symbol'],
                    'short_name' => $market['shortName'] ?? $market['symbol'],
                    'regular_market_price' => $market['regularMarketPrice']['raw'] ?? null,
                    'regular_market_change' => $market['regularMarketChange']['raw'] ?? null,
                    'regular_market_change_percent' => $market['regularMarketChangePercent']['raw'] ?? null,
                    'regular_market_time' => isset($market['regularMarketTime']) ? 
                        date('Y-m-d H:i:s', $market['regularMarketTime']) : null
                ];
            }
            return ['success' => true, 'markets' => $markets];
        }
        
        return ['error' => 'No market summary data available'];
    }
    
    /**
     * Get Trending Stocks
     * 
     * @return array Trending stocks
     */
    public function getTrendingStocks() {
        $result = $this->makeRequest('trending');
        
        if (isset($result['error'])) {
            return $result;
        }
        
        if (isset($result['finance']['result'][0]['quotes'])) {
            $trending = [];
            foreach ($result['finance']['result'][0]['quotes'] as $quote) {
                $trending[] = [
                    'symbol' => $quote['symbol'],
                    'short_name' => $quote['shortName'] ?? $quote['symbol'],
                    'regular_market_price' => $quote['regularMarketPrice'] ?? null,
                    'regular_market_change' => $quote['regularMarketChange'] ?? null,
                    'regular_market_change_percent' => $quote['regularMarketChangePercent'] ?? null
                ];
            }
            return ['success' => true, 'trending' => $trending];
        }
        
        return ['error' => 'No trending stocks data available'];
    }
    
    /**
     * Get Exchange Rate
     * 
     * @param string $from Source currency
     * @param string $to Target currency
     * @return array Exchange rate
     */
    public function getExchangeRate($from, $to) {
        $symbol = strtoupper($from) . strtoupper($to) . '=X';
        $result = $this->getQuote($symbol);
        
        if (isset($result['quote'])) {
            $quote = $result['quote'];
            return [
                'success' => true,
                'from' => strtoupper($from),
                'to' => strtoupper($to),
                'rate' => $quote['regular_market_price'],
                'change' => $quote['regular_market_change'],
                'change_percent' => $quote['regular_market_change_percent'],
                'timestamp' => $quote['regular_market_time']
            ];
        }
        
        return $result;
    }
    
    /**
     * Get Crypto Price
     * 
     * @param string $crypto Crypto symbol (BTC-USD, ETH-USD)
     * @return array Crypto data
     */
    public function getCryptoPrice($crypto) {
        $result = $this->getQuote($crypto);
        
        if (isset($result['quote'])) {
            $quote = $result['quote'];
            return [
                'success' => true,
                'symbol' => $quote['symbol'],
                'name' => $quote['short_name'],
                'price' => $quote['regular_market_price'],
                'change' => $quote['regular_market_change'],
                'change_percent' => $quote['regular_market_change_percent'],
                'volume' => $quote['volume'],
                'market_cap' => $quote['market_cap'],
                'timestamp' => $quote['regular_market_time']
            ];
        }
        
        return $result;
    }
    
    /**
     * Make API Request
     * 
     * @param string $endpoint Endpoint key
     * @param array $params Query parameters
     * @return array Response data
     */
    private function makeRequest($endpoint, $params = []) {
        if (!isset($this->endpoints[$endpoint])) {
            return ['error' => 'Invalid endpoint: ' . $endpoint];
        }
        
        $url = $this->endpoints[$endpoint];
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return ['error' => $error, 'code' => $httpCode];
        }
        
        $data = json_decode($response, true);
        
        if ($httpCode === 200 && $data !== null) {
            return $data;
        }
        
        return ['error' => 'Request failed with HTTP code: ' . $httpCode, 'code' => $httpCode];
    }
    
    /**
     * Parse Quote Data
     * 
     * @param array $data Raw quote data
     * @return array Parsed quote
     */
    private function parseQuote($data) {
        return [
            'symbol' => $data['symbol'],
            'short_name' => $data['shortName'] ?? $data['longName'] ?? $data['symbol'],
            'long_name' => $data['longName'] ?? null,
            'currency' => $data['currency'] ?? null,
            'market' => $data['market'] ?? null,
            'regular_market_price' => $data['regularMarketPrice'] ?? null,
            'regular_market_change' => $data['regularMarketChange'] ?? null,
            'regular_market_change_percent' => $data['regularMarketChangePercent'] ?? null,
            'regular_market_time' => isset($data['regularMarketTime']) ? 
                date('Y-m-d H:i:s', $data['regularMarketTime']) : null,
            'pre_market_price' => $data['preMarketPrice'] ?? null,
            'pre_market_change' => $data['preMarketChange'] ?? null,
            'pre_market_change_percent' => $data['preMarketChangePercent'] ?? null,
            'fifty_two_week_low' => $data['fiftyTwoWeekLow'] ?? null,
            'fifty_two_week_high' => $data['fiftyTwoWeekHigh'] ?? null,
            'volume' => $data['regularMarketVolume'] ?? null,
            'market_cap' => $data['marketCap'] ?? null,
            'trailing_pe' => $data['trailingPE'] ?? null,
            'exchange' => $data['exchange'] ?? null,
            'quote_type' => $data['quoteType'] ?? null,
            'market_state' => $data['marketState'] ?? null
        ];
    }
}