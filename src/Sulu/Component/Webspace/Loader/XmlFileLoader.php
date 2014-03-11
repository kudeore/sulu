<?php
/*
 * This file is part of the Sulu CMS.
 *
 * (c) MASSIVE ART WebServices GmbH
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sulu\Component\Webspace\Loader;

use Sulu\Component\Webspace\Environment;
use Sulu\Component\Webspace\Loader\Exception\InvalidUrlDefinitionException;
use Sulu\Component\Webspace\Localization;
use Sulu\Component\Webspace\Portal;
use Sulu\Component\Webspace\Segment;
use Sulu\Component\Webspace\Theme;
use Sulu\Component\Webspace\Url;
use Sulu\Component\Webspace\Webspace;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Config\Util\XmlUtils;

class XmlFileLoader extends FileLoader
{
    const SCHEME_PATH = '/schema/webspace/webspace-1.0.xsd';

    /**
     * @var  \DOMXPath
     */
    private $xpath;

    /**
     * The webspace which is created by this file loader
     * @var Webspace
     */
    private $webspace;

    /**
     * Loads a webspace from a xml file
     *
     * @param mixed $resource The resource
     * @param string $type     The resource type
     * @return Webspace The webspace object for the given resource
     */
    public function load($resource, $type = null)
    {
        $path = $this->getLocator()->locate($resource);

        // load data in path
        $webspace = $this->parseXml($path);

        return $webspace;
    }

    /**
     * Returns true if this class supports the given resource.
     *
     * @param mixed $resource A resource
     * @param string $type     The resource type
     *
     * @return Boolean true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'xml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    /**
     *
     * @param $file
     * @return Portal
     */
    private function parseXml($file)
    {
        // load xml file
        $xmlDoc = XmlUtils::loadFile($file, __DIR__ . static::SCHEME_PATH);
        $this->xpath = new \DOMXPath($xmlDoc);
        $this->xpath->registerNamespace('x', 'http://schemas.sulu.io/webspace/webspace');

        // set simple webspace properties
        $this->webspace = new Webspace();
        $this->webspace->setName($this->xpath->query('/x:webspace/x:name')->item(0)->nodeValue);
        $this->webspace->setKey($this->xpath->query('/x:webspace/x:key')->item(0)->nodeValue);
        $this->webspace->setTheme($this->generateTheme());

        // set localizations on webspaces
        $this->generateWebspaceLocalizations();

        // set segments on webspaces
        $this->generateSegments();

        // set portals on webspaces
        $this->generatePortals();

        return $this->webspace;
    }

    /**
     * @param \DOMNode $portalNode
     * @param Portal $portal
     */
    private function generatePortalLocalizations(\DOMNode $portalNode, Portal $portal)
    {
        if ($this->xpath->query('x:localizations', $portalNode)->length > 0) {
            // set localizations from portal, if they are set
            $localizationNodes = $this->xpath->query('x:localizations/x:localization', $portalNode);
            $this->generateLocalizationsFromNodeList($localizationNodes, $portal);
        } else {
            // if the portal has no localizations fallback to the localizations from the webspace
            $localizationNodes = $this->xpath->query('/x:webspace/x:localizations//x:localization');
            $this->generateLocalizationsFromNodeList($localizationNodes, $portal, true);
        }
    }

    /**
     * @param \DOMNodeList $localizationNodes
     * @param Portal $portal
     * @param bool $flat
     * @internal param \DOMXpath $xpath
     */
    private function generateLocalizationsFromNodeList(\DOMNodeList $localizationNodes, Portal $portal, $flat = false)
    {
        foreach ($localizationNodes as $localizationNode) {
            $localization = $this->generateLocalizationFromNode($localizationNode, $flat);

            $portal->addLocalization($localization);
        }
    }

    /**
     * @param \DOMElement|\DOMNode $localizationNode
     * @param bool $flat
     * @param null $parent
     * @internal param \DOMXPath $xpath
     * @return Localization
     */
    private function generateLocalizationFromNode(\DOMElement $localizationNode, $flat = false, $parent = null)
    {
        $localization = new Localization();
        $localization->setLanguage($localizationNode->attributes->getNamedItem('language')->nodeValue);

        // set parent if given
        if ($parent) {
            $localization->setParent($parent);
        }

        // set optional nodes
        $countryNode = $localizationNode->attributes->getNamedItem('country');
        if ($countryNode) {
            $localization->setCountry($countryNode->nodeValue);
        }

        $shadowNode = $localizationNode->attributes->getNamedItem('shadow');
        if ($shadowNode) {
            $localization->setShadow($shadowNode->nodeValue);
        }

        // set child nodes
        if (!$flat) {
            foreach ($this->xpath->query('x:localization', $localizationNode) as $childNode) {
                $localization->addChild($this->generateLocalizationFromNode($childNode, $flat, $localization));
            }
        }

        return $localization;
    }

    private function generateWebspaceLocalizations()
    {
        foreach ($this->xpath->query('/x:webspace/x:localizations/x:localization') as $localizationNode) {
            $localization = $this->generateLocalizationFromNode($localizationNode);

            $this->webspace->addLocalization($localization);
        }
    }

    private function generateSegments()
    {
        foreach ($this->xpath->query('/x:webspace/x:segments/x:segment') as $segmentNode) {
            /** @var \DOMNode $segmentNode */
            $segment = new Segment();
            $segment->setName($segmentNode->nodeValue);
            $segment->setKey($segmentNode->attributes->getNamedItem('key')->nodeValue);

            $this->webspace->addSegment($segment);
        }
    }

    private function generatePortals()
    {
        foreach ($this->xpath->query('/x:webspace/x:portals/x:portal') as $portalNode) {
            /** @var \DOMNode $portalNode */
            $portal = new Portal();

            $portal->setName($this->xpath->query('x:name', $portalNode)->item(0)->nodeValue);
            $portal->setKey($this->xpath->query('x:key', $portalNode)->item(0)->nodeValue);
            $portal->setResourceLocatorStrategy(
                $this->xpath->query('x:resource-locator/x:strategy', $portalNode)->item(0)->nodeValue
            );

            // set localization on portal
            $this->generatePortalLocalizations($portalNode, $portal);

            $this->webspace->addPortal($portal);

            // set environments
            $this->generateEnvironments($portalNode, $portal);
        }
    }

    /**
     * @internal param \DOMNode $webspaceNode
     * @return Theme
     */
    private function generateTheme()
    {
        $theme = new Theme();
        $theme->setKey($this->xpath->query('/x:webspace/x:theme/x:key')->item(0)->nodeValue);

        foreach ($this->xpath->query('/x:webspace/x:theme/x:excluded/x:template') as $templateNode) {
            /** @var \DOMNode $templateNode */
            $theme->addExcludedTemplate($templateNode->nodeValue);
        }

        return $theme;
    }

    /**
     * @param \DOMNode $portalNode
     * @param Portal $portal
     */
    private function generateEnvironments(\DOMNode $portalNode, Portal $portal)
    {
        foreach ($this->xpath->query('x:environments/x:environment', $portalNode) as $environmentNode) {
            /** @var \DOMNode $environmentNode */
            $environment = new Environment();
            $environment->setType($environmentNode->attributes->getNamedItem('type')->nodeValue);

            $this->generateUrls($environmentNode, $environment);

            $portal->addEnvironment($environment);
        }
    }

    /**
     * @param \DOMNode $environmentNode
     * @param Environment $environment
     * @throws Exception\InvalidUrlDefinitionException
     */
    private function generateUrls(\DOMNode $environmentNode, Environment $environment)
    {
        foreach ($this->xpath->query('x:urls/x:url', $environmentNode) as $urlNode) {
            // check if the url is valid, and throw an exception otherwise
            if (!$this->checkUrlNode($urlNode)) {
                throw new InvalidUrlDefinitionException($this->webspace, $urlNode->nodeValue);
            }

            /** @var \DOMNode $urlNode */
            $url = new Url();

            $url->setUrl($urlNode->nodeValue);

            // set optional nodes
            $url->setLanguage($this->getOptionalNodeAttribute($urlNode, 'language'));
            $url->setCountry($this->getOptionalNodeAttribute($urlNode, 'country'));
            $url->setSegment($this->getOptionalNodeAttribute($urlNode, 'segment'));
            $url->setRedirect($this->getOptionalNodeAttribute($urlNode, 'redirect'));

            $environment->addUrl($url);
        }
    }

    private function getOptionalNodeAttribute(\DOMNode $node, $name)
    {
        $attribute = $node->attributes->getNamedItem($name);
        if ($attribute) {
            return $attribute->nodeValue;
        }

        return null;
    }

    /**
     * Checks if the urlNode is valid for this webspace
     * @param \DOMNode $urlNode
     * @return bool
     */
    private function checkUrlNode(\DOMNode $urlNode)
    {
        $hasLocalization = ($urlNode->attributes->getNamedItem('localization') != null)
            || (strpos($urlNode->nodeValue, '{localization}') !== false);

        $hasLanguage = ($urlNode->attributes->getNamedItem('language') != null)
            || (strpos($urlNode->nodeValue, '{language}') !== false)
            || $hasLocalization;

        $hasCountry = ($urlNode->attributes->getNamedItem('country') != null)
            || (strpos($urlNode->nodeValue, '{country}') !== false)
            || $hasLocalization;

        $hasSegment = (count($this->webspace->getSegments()) == 0)
            || ($urlNode->attributes->getNamedItem('segment') != null)
            || (strpos($urlNode->nodeValue, '{segment}') !== false);

        $hasRedirect = ($urlNode->attributes->getNamedItem('redirect') != null);

        return ($hasLanguage && $hasCountry && $hasSegment) || $hasRedirect;
    }
}
