<?php

namespace Craft;

class FbPixel_AddToCartService extends BaseApplicationComponent
{
    const FLASH_NAME = '_fbPixelVariantIds';

    private $variantIds;

    public function listen()
    {
        craft()->on('multiAdd_cart.MultiAddToCart', [
            $this, 'onMultiAddToCartHandler'
        ]);

        craft()->on('commerce_cart.onAddToCart', [
            $this, 'onAddToCartHandler'
        ]);

        craft()->fbPixel_addToCart->checkFlash();
    }

    public function checkFlash()
    {
        if (craft()->userSession->hasFlash(self::FLASH_NAME)) {
            $this->variantIds = craft()->userSession->getFlash(self::FLASH_NAME, null, true);
            $this->addHook();
        }
    }

    public function addHook()
    {
        craft()->templates->hook('fbPixel.renderBase', [
            $this, 'renderTemplate'
        ]);
    }

    public function onAddToCartHandler($event)
    {
        $lineItem = $event->params['lineItem'];
        $this->addFlash([$lineItem]);
    }

    public function onMultiAddToCartHandler($event)
    {
        $lineItems = $event->params['lineItems'];
        $this->addFlash($lineItems);
    }

    public function addFlash($lineItems)
    {
        $variantIds = craft()->userSession->getFlash(self::FLASH_NAME);

        if (empty($variantIds)) {
            $variantIds = [];
        }

        $variantIds = array_merge(
            $variantIds,
            $this->getVariantIds($lineItems)
        );

        craft()->userSession->setFlash(self::FLASH_NAME, $variantIds);
    }

    public function renderTemplate()
    {
        $template = '';

        foreach ($this->variantIds as $variantId) {
            $variant = craft()->commerce_variants->getVariantById($variantId);

            $eventData = [
                'value' => $variant->salePrice,
                'currency' => 'EUR',
                'content_name' => 'Add To Cart',
                'content_ids' => $variant->sku,
                'content_type' => 'product'
            ];

            $template .= craft()->fbPixel->renderEvent('AddToCart', $eventData);
        }

        return $template;
    }

    private function getVariantIds($lineItems)
    {
        return array_map( function($lineItem) {
            $purchasable = $lineItem->purchasable;

            if (!empty($purchasable->defaultVariant)) {
                return $purchasable->defaultVariant->id;
            } else {
                return $purchasable->id;
            }

        }, $lineItems);
    }
}
