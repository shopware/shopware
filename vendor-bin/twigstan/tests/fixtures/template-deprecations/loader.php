<?php declare(strict_types=1);

use Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan\TemplateDeprecationNodeVisitor;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

require dirname(__DIR__, 3) . '/bootstrap.php';

$twig = new Environment(new FilesystemLoader(__DIR__));
$twig->addNodeVisitor(new TemplateDeprecationNodeVisitor(['base.twig', 'child.twig', 'input.twig']));

return $twig;
