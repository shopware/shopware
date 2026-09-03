<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerRecovery\CustomerRecoveryDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerTag\CustomerTagDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlistProduct\CustomerWishlistProductDefinition;
use Shopware\Core\Checkout\Customer\Api\ConvertGuestController;
use Shopware\Core\Checkout\Customer\Api\CustomerGroupRegistrationActionController;
use Shopware\Core\Checkout\Customer\CleanupCustomerRecoveryTask;
use Shopware\Core\Checkout\Customer\CleanupCustomerRecoveryTaskHandler;
use Shopware\Core\Checkout\Customer\Command\DeleteUnusedGuestCustomersCommand;
use Shopware\Core\Checkout\Customer\Cookie\WishlistCookieCollectListener;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\CustomerValueResolver;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer;
use Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerWishlistProductExceptionHandler;
use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerHandler;
use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerService;
use Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerTask;
use Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\Md5;
use Shopware\Core\Checkout\Customer\Password\LegacyEncoder\Sha256;
use Shopware\Core\Checkout\Customer\Password\LegacyPasswordVerifier;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountNewsletterRecipientRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\AccountService;
use Shopware\Core\Checkout\Customer\SalesChannel\AddWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeCustomerProfileRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeEmailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangeLanguageRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ChangePasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerGroupRegistrationSettingsRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRecoveryIsExpiredRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\CustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\DeleteAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\DeleteCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\DownloadRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ImitateCustomerRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ListAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoadWishlistRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LoginRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\LogoutRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\MergeWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RemoveWishlistProductRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\ResetPasswordRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\SalesChannelCustomerAddressDefinition;
use Shopware\Core\Checkout\Customer\SalesChannel\SendPasswordRecoveryMailRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\SwitchDefaultAddressRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\UpsertAddressRoute;
use Shopware\Core\Checkout\Customer\Service\DoubleOptInService;
use Shopware\Core\Checkout\Customer\Service\GuestAuthenticator;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Checkout\Customer\Subscriber\AddressHashSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerAddressSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerBeforeDeleteSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerChangePasswordSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerEmailUniqueSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerFlowEventsSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerLanguageSalesChannelSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerLogoutSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerMetaFieldSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerRemoteAddressSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerSalutationSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerTokenSubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\CustomerVatIdCountrySubscriber;
use Shopware\Core\Checkout\Customer\Subscriber\ProductReviewSubscriber;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUniqueValidator;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerPasswordMatchesValidator;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCodeValidator;
use Shopware\Core\Checkout\Customer\Validation\CustomerEmailUniqueChecker;
use Shopware\Core\Checkout\Customer\Validation\CustomerProfileValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\PasswordValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\VatIdPatternProvider;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\NumberRange\ValueGenerator\NumberRangeValueGeneratorInterface;
use Shopware\Core\System\SalesChannel\Context\CartRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('customer.account_types', [
            CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            CustomerEntity::ACCOUNT_TYPE_PRIVATE,
        ]);

    $services = $containerConfigurator->services();

    $services->set(CustomerDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(CustomerGroupTranslationDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomerAddressDefinition::class)
        ->tag('shopware.entity.definition')
        ->tag('shopware.entity.hookable');

    $services->set(CustomerRecoveryDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomerGroupDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomerGroupRegistrationSalesChannelDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomerTagDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(AccountService::class)
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(LegacyPasswordVerifier::class),
            service(SwitchDefaultAddressRoute::class),
            service(CartRestorer::class),
            service(DoubleOptInService::class),
            service(ClockInterface::class),
        ]);

    $services->set(DoubleOptInService::class)
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service('sales_channel_domain.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(AddressValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(CustomerProfileValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
            param('customer.account_types'),
        ]);

    $services->set(PasswordValidationFactory::class)
        ->args([
            service(SystemConfigService::class),
        ]);

    $services->set(CustomerValidationFactory::class)
        ->args([
            service(CustomerProfileValidationFactory::class),
        ]);

    $services->set(CustomerEmailUniqueChecker::class)
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ]);

    $services->set(CustomerEmailUniqueValidator::class)
        ->args([
            service(CustomerEmailUniqueChecker::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(CustomerPasswordMatchesValidator::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(VatIdPatternProvider::class)
        ->args([
            service(Connection::class),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(CustomerVatIdentificationValidator::class)
        ->args([
            service(VatIdPatternProvider::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(CustomerZipCodeValidator::class)
        ->args([
            service('country.repository'),
        ])
        ->tag('validator.constraint_validator');

    $services->set(Md5::class)
        ->tag('shopware.legacy_encoder');

    $services->set(Sha256::class)
        ->tag('shopware.legacy_encoder');

    $services->set(LegacyPasswordVerifier::class)
        ->args([
            tagged_iterator('shopware.legacy_encoder'),
        ]);

    $services->set(AddressHashSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomerMetaFieldSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ProductReviewCountService::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(GuestAuthenticator::class);

    $services->set(ProductReviewSubscriber::class)
        ->args([
            service(ProductReviewCountService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerRemoteAddressSubscriber::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerTokenSubscriber::class)
        ->args([
            service(SalesChannelContextPersister::class),
            service('request_stack'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerChangePasswordSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerFlowEventsSubscriber::class)
        ->args([
            service(EventDispatcherInterface::class),
            service(SalesChannelContextRestorer::class),
            service(CustomerIndexer::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerLogoutSubscriber::class)
        ->args([
            service(RequestStack::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(LoginRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(RequestStack::class),
            service(RateLimiter::class),
        ]);

    $services->set(LogoutRoute::class)
        ->public()
        ->args([
            service(SalesChannelContextPersister::class),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(CartService::class),
            service(SalesChannelContextService::class),
        ]);

    $services->set(SendPasswordRecoveryMailRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(SystemConfigService::class),
            service(RequestStack::class),
            service('shopware.rate_limiter'),
        ]);

    $services->set(ResetPasswordRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(RequestStack::class),
            service('shopware.rate_limiter'),
            service(PasswordValidationFactory::class),
            service(ClockInterface::class),
        ]);

    $services->set(CustomerRecoveryIsExpiredRoute::class)
        ->public()
        ->args([
            service('customer_recovery.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(ClockInterface::class),
            service(SystemConfigService::class),
            service(RequestStack::class),
            service('shopware.rate_limiter'),
        ]);

    $services->set(ChangeCustomerProfileRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(CustomerProfileValidationFactory::class),
            service(StoreApiCustomFieldMapper::class),
            service('salutation.repository'),
        ]);

    $services->set(ChangePasswordRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(DataValidator::class),
        ]);

    $services->set(ChangeEmailRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service('customer_recovery.repository'),
        ]);

    $services->set(ChangeLanguageRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
        ]);

    $services->set(ConvertGuestRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(PasswordValidationFactory::class),
            service(RequestStack::class),
            service('shopware.rate_limiter'),
        ]);

    $services->set(CustomerRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
        ]);

    $services->set(DeleteCustomerRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
        ]);

    $services->set(RegisterRoute::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(NumberRangeValueGeneratorInterface::class),
            service(DataValidator::class),
            service(CustomerValidationFactory::class),
            service(AddressValidationFactory::class),
            service(SystemConfigService::class),
            service('customer.repository'),
            service(SalesChannelContextPersister::class),
            service('sales_channel.country.repository'),
            service(Connection::class),
            service(SalesChannelContextService::class),
            service(StoreApiCustomFieldMapper::class),
            service('salutation.repository'),
            service(PasswordValidationFactory::class),
            service(DoubleOptInService::class),
            service(CustomerNewsletterSalesChannelsUpdater::class),
            service(ClockInterface::class),
        ]);

    $services->set(RegisterConfirmRoute::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('event_dispatcher'),
            service(DataValidator::class),
            service(SalesChannelContextPersister::class),
            service(SalesChannelContextService::class),
            service(ClockInterface::class),
        ]);

    $services->set(ListAddressRoute::class)
        ->public()
        ->args([
            service('sales_channel.customer_address.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(UpsertAddressRoute::class)
        ->public()
        ->args([
            service('customer_address.repository'),
            service('sales_channel.customer_address.repository'),
            service(DataValidator::class),
            service('event_dispatcher'),
            service(AddressValidationFactory::class),
            service(SystemConfigService::class),
            service(StoreApiCustomFieldMapper::class),
            service('salutation.repository'),
        ]);

    $services->set(DeleteAddressRoute::class)
        ->public()
        ->args([
            service('customer_address.repository'),
        ]);

    $services->set(SwitchDefaultAddressRoute::class)
        ->public()
        ->args([
            service('customer_address.repository'),
            service('customer.repository'),
            service(EventDispatcherInterface::class),
        ]);

    $services->set(CustomerGroupRegistrationSettingsRoute::class)
        ->public()
        ->args([
            service('customer_group.repository'),
        ]);

    $services->set(SalesChannelCustomerAddressDefinition::class)
        ->tag('shopware.sales_channel.entity.definition');

    $services->set(CustomerIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('customer.repository'),
            service(ManyToManyIdFieldUpdater::class),
            service(CustomerNewsletterSalesChannelsUpdater::class),
            service('event_dispatcher'),
        ])
        ->tag('shopware.entity_indexer', ['priority' => 100]);

    $services->set(ConvertGuestController::class)
        ->public()
        ->args([
            service('customer.repository'),
            service(SalesChannelContextService::class),
            service(ConvertGuestRoute::class),
            service(SendPasswordRecoveryMailRoute::class),
            service(Connection::class),
        ]);

    $services->set(CustomerGroupRegistrationActionController::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_group.repository'),
            service('event_dispatcher'),
            service(SalesChannelContextRestorer::class),
        ]);

    $services->set(CustomerWishlistDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(CustomerWishlistProductDefinition::class)
        ->tag('shopware.entity.definition');

    $services->set(LoadWishlistRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('sales_channel.product.repository'),
            service('event_dispatcher'),
            service(SystemConfigService::class),
            service(ProductCloseoutFilterFactory::class),
        ]);

    $services->set(AddWishlistProductRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(RemoveWishlistProductRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('customer_wishlist_product.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ]);

    $services->set(CustomerWishlistProductExceptionHandler::class)
        ->tag('shopware.dal.exception_handler');

    $services->set(MergeWishlistProductRoute::class)
        ->public()
        ->args([
            service('customer_wishlist.repository'),
            service('sales_channel.product.repository'),
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(Connection::class),
        ]);

    $services->set(WishlistCookieCollectListener::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(CustomerValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1002]);

    $services->set(AccountNewsletterRecipientRoute::class)
        ->public()
        ->args([
            service('sales_channel.newsletter_recipient.repository'),
        ]);

    $services->set(ImitateCustomerRoute::class)
        ->public()
        ->args([
            service(AccountService::class),
            service(ImitateCustomerTokenGenerator::class),
            service(LogoutRoute::class),
            service(SalesChannelContextFactory::class),
            service('event_dispatcher'),
            service(DataValidator::class),
        ]);

    $services->set(DeleteUnusedGuestCustomerService::class)
        ->args([
            service('customer.repository'),
            service(SystemConfigService::class),
        ]);

    $services->set(ImitateCustomerTokenGenerator::class)
        ->args([
            env('APP_SECRET'),
            service('shopware.jwt_config'),
            service(DataValidator::class),
            service(ClockInterface::class),
        ]);

    $services->set(DeleteUnusedGuestCustomersCommand::class)
        ->args([
            service(DeleteUnusedGuestCustomerService::class),
        ])
        ->tag('console.command');

    $services->set(DeleteUnusedGuestCustomerTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(DeleteUnusedGuestCustomerHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(DeleteUnusedGuestCustomerService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CleanupCustomerRecoveryTask::class)
        ->tag('shopware.scheduled.task');

    $services->set(CleanupCustomerRecoveryTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(CustomerBeforeDeleteSubscriber::class)
        ->args([
            service('customer.repository'),
            service('sales_channel.repository'),
            service(SalesChannelContextService::class),
            service('event_dispatcher'),
            service(JsonEntityEncoder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerLanguageSalesChannelSubscriber::class)
        ->args([
            service('sales_channel.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerEmailUniqueSubscriber::class)
        ->args([
            service(Connection::class),
            service(CustomerEmailUniqueChecker::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DownloadRoute::class)
        ->public()
        ->args([
            service('order_line_item_download.repository'),
            service(DownloadResponseGenerator::class),
        ]);

    $services->set(CustomerSalutationSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomerAddressSubscriber::class)
        ->tag('kernel.event_subscriber');

    $services->set(CustomerVatIdCountrySubscriber::class)
        ->args([
            service(VatIdPatternProvider::class),
        ])
        ->tag('kernel.event_subscriber');
};
