<?php

namespace App\Command;


use Closure;
use ErrorException;
use VK\Client\VKApiClient;
use VK\Exceptions\Api\VKApiAccessException;
use VK\Exceptions\Api\VKApiBlockedException;
use VK\Exceptions\Api\VKApiTooManyException;
use VK\Exceptions\Api\VKApiWallAccessRepliesException;
use VK\Exceptions\VKApiException;
use VK\Exceptions\VKClientException;

trait CommandTrait
{
    protected static int $counterRequests = 0;
    protected static int $countRequest = 0;
    protected static ?float $lastTimeRequest = null;
    protected static ?float $prevTimeRequest = null;
    protected static array $logLimit = [];

    /**
     * @return void
     */
    protected function limitRequest(): void
    {
        self::$counterRequests++;
        self::$countRequest++;
        $currentTime = microtime(true);

        if (null === self::$lastTimeRequest) {
            self::$lastTimeRequest = $currentTime;
        }

        if (null === self::$prevTimeRequest) {
            self::$prevTimeRequest = $currentTime;
        }

        $diffTimeRequests = self::$lastTimeRequest - self::$prevTimeRequest;
        $diffTimeCurrent = $currentTime - self::$lastTimeRequest;

        self::$logLimit = array_slice(self::$logLimit, -10, 10);

        if ($diffTimeCurrent < 1 && $diffTimeRequests < 1 && self::$countRequest > $_ENV['VK_API_LIMIT_PER_SEC']) {

            self::$logLimit[] = [
                "sleep" => true,
                "diffTimeCurrent" => $diffTimeCurrent,
                "diffTimeRequests" => $diffTimeRequests,
                "countRequest" => self::$countRequest,
                "currentTime" => $currentTime,
                "lastTimeRequest" => self::$lastTimeRequest,
                "prevTimeRequest" => self::$prevTimeRequest,
            ];
            sleep(1);
            self::$countRequest = 0;
        } else {

            self::$logLimit[] = [
                "sleep" => false,
                "diffTimeCurrent" => $diffTimeCurrent,
                "diffTimeRequests" => $diffTimeRequests,
                "countRequest" => self::$countRequest,
                "currentTime" => $currentTime,
                "lastTimeRequest" => self::$lastTimeRequest,
                "prevTimeRequest" => self::$prevTimeRequest,
            ];
        }

        if ($diffTimeCurrent >= 1) {
            self::$countRequest = 0;
        }

        self::$prevTimeRequest = self::$lastTimeRequest;
        self::$lastTimeRequest = microtime(true);
    }

    /**
     * @param Closure $func
     * @param array $params
     * @return array
     */
    protected function wrapperRequest(Closure $func, array $params): array
    {
        $response = [];

        try {
            $this->limitRequest();
            $response = $func($params);
        } catch (VKApiAccessException $e) {
            $this->logger->error('VKApiAccessException', $params);
        } catch (VKApiTooManyException $e) {
            $this->logger->error('VKApiTooManyException', ["params" => $params, "logLimit" => self::$logLimit]);
            sleep(10);
        }

        return $response;
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getComments(array $params): array
    {

        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKApiAccessException
         * @throws VKApiTooManyException
         * @throws VKClientException
         * @throws VKApiWallAccessRepliesException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);

            return $vk->wall()->getComments($_ENV['VK_API_TOKEN'], $params);
        };

        return $this->wrapperRequest($func, $params);
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getLikes(array $params): array
    {

        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKApiAccessException
         * @throws VKApiTooManyException
         * @throws VKClientException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);
            return $vk->likes()->getList($_ENV['VK_API_TOKEN'], $params);

        };

        return $this->wrapperRequest($func, $params);
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getUsers(array $params): array
    {

        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKApiAccessException
         * @throws VKApiTooManyException
         * @throws VKClientException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);
            return $vk->users()->get($_ENV['VK_API_TOKEN'], $params);

        };

        return $this->wrapperRequest($func, $params);
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getGroups(array $params): array
    {

        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKApiAccessException
         * @throws VKApiTooManyException
         * @throws VKClientException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);
            return $vk->groups()->getById($_ENV['VK_API_TOKEN'], $params);

        };

        return $this->wrapperRequest($func, $params);
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getPosts(array $params): array
    {

        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKApiAccessException
         * @throws VKApiTooManyException
         * @throws VKClientException
         * @throws VKApiBlockedException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);
            return $vk->wall()->get($_ENV['VK_API_TOKEN'], $params);

        };

        return $this->wrapperRequest($func, $params);
    }

    /**
     * @param array $params
     * @return array
     */
    protected function getPostsByIds(array $params): array
    {

        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKApiAccessException
         * @throws VKApiTooManyException
         * @throws VKClientException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);
            return $vk->wall()->getById($_ENV['VK_API_TOKEN'], $params);

        };

        return $this->wrapperRequest($func, $params);
    }


    /**
     * @param array $params
     * @return array
     */
    protected function resolveScreenName(array $params): array
    {
        /**
         * @param $params
         * @return mixed
         * @throws VKApiException
         * @throws VKClientException
         */
        $func = function ($params) {

            $vk = new VKApiClient($_ENV['VK_API_VERSION']);
            return $vk->utils()->resolveScreenName($_ENV['VK_API_TOKEN'], $params);

        };

        return $this->wrapperRequest($func, $params);
    }

    /**
     * @param string $screenName
     * @return int
     * @throws ErrorException
     */
    private function getObjectId(string $screenName): int
    {
        $response = $this->resolveScreenName(["screen_name" => $screenName]);

        if (empty($response['object_id'])) {

            throw new ErrorException(sprintf('Id [%s] not Found', $screenName));
        }

        if ('group' === $response['type'] || 'page' === $response['type']) {
            return -$response['object_id'];
        } elseif ('user' === $response['type']) {
            return $response['object_id'];
        }

        throw new ErrorException('Id not user, group, page');
    }
}