<?php
namespace Trellis\Salsify\Controller\Hook;

use Magento\Framework\Exception\LocalizedException;

class Update extends AbstractHook
{
    private const PUBLICATION_STATUS_COMPLETED = 'completed';

    /**
     * @param $payload
     * @return mixed|void
     * @throws LocalizedException
     */
    public function hook($payload)
    {
        if (!$this->verifyWebhook($this->getRequest())) {
            $this->_logger->info("Invalid webhook");

            return false;
        }

        try {
            $this->_logger->info('Entered publish webhook. Payload sent: ' . $this->serializer->serialize($payload));
            if ($payload === null) {
                $this->_logger->info('I can\'t find a suitable payload, aborting...');
                return;
            }

            if (!array_key_exists('publication_status', $payload)) {
                $this->_logger->info('Payload is missing publication_status key, aborting...');
                return;
            }
            if ($payload['publication_status'] === self::PUBLICATION_STATUS_COMPLETED) {
                $this->_logger->info('Publication status was completed, setting the lockFlag for cron...');

                // This stores the payload information from the webhook when the readiness report is published.
                $payloadData['payload'] = $this->serializer->serialize($payload);
                $payloadData['processed'] = '0';
                $payload = $this->_payloadFactory->create();
                $payload->setData($payloadData);

                $this->_logger->info('Saving payload for cron to pickup...');
                $this->payloadRepository->save($payload);
            }
            $this->_logger->info('Exiting publish webhook.');
        } catch (\Exception $e) {
            $this->_logger->error('Something happened while running Salsify sync via webhook :(' . $e->getMessage());
        }
    }
}
