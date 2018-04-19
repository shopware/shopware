<?php
class Migrations_Migration436 Extends Shopware\Framework\Migration\AbstractMigration
{
    public function up($modus)
    {
        $sql = <<<'EOD'
SET @parent = (SELECT id FROM `s_library_component` WHERE `cls`='emotion--element-video' LIMIT 1);
EOD;
        $this->addSql($sql);

        $sql = <<<SQL
DELETE FROM s_library_component_field WHERE componentID = @parent;
SQL;
        $this->addSql($sql);

        $sql = <<<'EOD'
INSERT IGNORE INTO `s_library_component_field` (`componentID`, `name`, `x_type`, `value_type`, `field_label`, `support_text`, `help_title`, `help_text`, `store`, `display_field`, `value_field`, `default_value`, `allow_blank`, `position`) VALUES
(@parent, 'videoMode', 'emotion-components-fields-video-mode', '', 'Modus', 'Bestimmen Sie das Verhalten des Videos. Legen Sie fest, ob das Video skalierend, füllend oder gestreckt dargestellt werden soll.', '', '', '', 'label', 'key', '', 0, 40),
(@parent, 'overlay', 'textfield', '', 'Overlay Farbe', 'Legen Sie eine Hintergrundfarbe für das Overlay fest. Ein RGBA-Wert wird empfohlen.', '', '', '', '', '', 'rgba(0, 0, 0, .2)', 1, 71),
(@parent, 'originTop', 'numberfield', '', 'Oberer Ausgangspunkt', 'Legt den oberen Ausgangspunkt für die Skalierung des Videos fest. Die Angabe erfolgt in Prozent.', '', '', '', '', '', '50', 1, 69),
(@parent, 'originLeft', 'numberfield', '', 'Linker Ausgangspunkt', 'Legt den linken Ausgangspunkt für die Skalierung des Videos fest. Die Angabe erfolgt in Prozent.', '', '', '', '', '', '50', 1, 68),
(@parent, 'scale', 'numberfield', '', 'Zoom-Faktor', 'Wenn Sie den Modus Füllen gewählt haben können Sie den Zoom-Faktor mit dieser Option ändern.', '', '', '', '', '', '1.0', 1, 67),
(@parent, 'muted', 'checkbox', '', 'Video stumm schalten', 'Die Ton-Spur des Videos wird stumm geschaltet', '', '', '', '', '', '1', 1, 60),
(@parent, 'loop', 'checkbox', '', 'Video schleifen', 'Das Video wird in einer Dauerschleife angezeigt', '', '', '', '', '', '1', 1, 59),
(@parent, 'controls', 'checkbox', '', 'Video-Steuerung anzeigen', 'Nicht für den Modus Füllen oder Strecken empfohlen.', '', '', '', '', '', '1', 1, 58),
(@parent, 'autobuffer', 'checkbox', '', 'Video automatisch vorladen', '', '', '', '', '', '', '1', 1, 57),
(@parent, 'autoplay', 'checkbox', '', 'Video automatisch abspielen', '', '', '', '', '', '', '1', 1, 56),
(@parent, 'html_text', 'tinymce', '', 'Overlay Text', 'Sie können ein Overlay mit einem Text über das Video legen.', '', '', '', '', '', '', 1, 70),
(@parent, 'fallback_picture', 'mediatextfield', '', 'Vorschau-Bild', 'Das Vorschau-Bild wird gezeigt wenn das Video noch nicht abgespielt wird.', '', '', '', '', '', '', 0, 44),
(@parent, 'h264_video', 'mediatextfield', '', '.mp4 Video', 'Video für Browser mit MP4 Support. Auch externer Pfad möglich.', '', '', '', '', '', '', 0, 43),
(@parent, 'ogg_video', 'mediatextfield', '', '.ogv/.ogg Video', 'Video für Browser mit Ogg Support. Auch externer Pfad möglich.', '', '', '', '', '', '', 0, 42),
(@parent, 'webm_video', 'mediatextfield', '', '.webm Video', 'Video für Browser mit WebM Support. Auch externer Pfad möglich.', '', '', '', '', '', '', 0, 41);
EOD;
        $this->addSql($sql);
    }
}
