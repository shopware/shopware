<?php
/**
 * Created by PhpStorm.
 * User: j.buecker
 * Date: 09.04.18
 * Time: 14:32
 */

namespace Shopware\Framework\Application;

use Symfony\Component\HttpFoundation\Request;

interface ApplicationResolverInterface
{
    /**
     * @param Request $request
     * @param ApplicationInfo $appInfo
     *
     * @throws ApplicationNotFoundException
     */
    public function resolveApplication(Request $request, ApplicationInfo $appInfo): void;

    public function resolveContextToken(Request $request, ApplicationInfo $appInfo): void;
}