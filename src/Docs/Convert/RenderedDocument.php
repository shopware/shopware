<?php declare(strict_types=1);

namespace Shopware\Docs\Convert;

class RenderedDocument
{
    private const GLOBAL_SCRIPT_CONTENT = <<<EOD

<script>

function TabPanel(containerElement) {
  this.container = containerElement;
  this.tabNav = this.container.querySelector("nav");
  this.contentContainer = this.container.querySelector(".tabs-container");

  this.init();
}

TabPanel.prototype.init = function() {
  this.setActivePage(0);
  this.registerEventListeners();
};

TabPanel.prototype.registerEventListeners = function() {
  this.tabNav.addEventListener(
    "click",
    TabPanel.prototype.onTitleClick.bind(this),
    false
  );
};

TabPanel.prototype.onTitleClick = function(event) {
  event.preventDefault();
  const target = event.target;
  const pageNum = Array.from(this.tabNav.children).indexOf(target);

  if (pageNum === -1) {
    return false;
  }

  this.setActivePage(pageNum);
};

TabPanel.prototype.setActivePage = function(pageNum) {
  if (isNaN(pageNum)) {
    return false;
  }

  const navLinks = Array.from(this.tabNav.querySelectorAll("a"));
  const contentPanels = Array.from(
    this.contentContainer.querySelectorAll("section")
  );
  [...navLinks, ...contentPanels].forEach(item => {
    item.classList.remove("is-active");
  });

  const title = this.tabNav.children[pageNum];
  const content = this.contentContainer.children[pageNum];

  [title, content].forEach(item => {
    if (!item) {
      return false;
    }
    item.classList.add("is-active");
  });
};

Array.from(document.querySelectorAll(".tabs")).forEach(tab => {
  new TabPanel(tab);
});

</script>
EOD;

    private const GLOBAL_STYLE_CONTENT = <<<EOD
<style type="text/css">

dl dt {
    font-weight: bolder;
    margin-top: 1rem;
}

dl dd {
    padding-left: 2rem;
}

h2 code {
    font-size: 32px;
}

.category--description ul {
    padding-left: 2rem;
}

dt code,
li code,
table code,
p code {
    font-family: monospace, monospace;
    background-color: #f9f9f9;
    font-size: 16px;
}

.tabs nav a {
    background-color: #fff;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
    color: #142432;
    display: inline-block;
    padding: 5px 12px 3px;
    text-decoration: none;
    border: 1px solid #d8dde6;
    position: relative;
    bottom: -1px;
}

.tabs .tabs-container {
    background-color: #f4f7fa;
    padding-left: 12px;
    border: 1px solid #d8dde6;
}

.tabs section code {
    background: #f4f7fa;
}

.tabs section {
    padding: 10px;
    background: #f4f7fa;
}

.tabs .tabs-container pre {
    margin-top: 0;
    padding: 0;
}

.tabs nav a.is-active {
    background-color: #f4f7fa;
    text-decoration: none;
    border-bottom-color: #f4f7fa;
    font-weight: bold;
}

.tabs-container > section {
    display: none;
}
.tabs-container > .is-active {
    display: block;
}

</style>

EOD;

    /**
     * @var string
     */
    private $html;

    /**
     * @var array
     */
    private $images;

    public function __construct(string $html, array $images)
    {
        $this->html = $html;
        $this->images = $images;
    }

    public function addImage(string $key, string $path): void
    {
        $this->images[$key] = $path;
    }

    public function getContents(array $imageMap = []): string
    {
        $result = $this->html;

        foreach ($imageMap as $key => $link) {
            $result = str_replace($key, $link, $result);
        }

        return self::GLOBAL_STYLE_CONTENT . $result . self::GLOBAL_SCRIPT_CONTENT;
    }

    public function getImages(): array
    {
        return $this->images;
    }
}
