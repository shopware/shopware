import { generateJSON } from '@tiptap/core';
import Document from '@tiptap/extension-document';
import Heading from '@tiptap/extension-heading';
import Paragraph from '@tiptap/extension-paragraph';
import TextStyle from '@tiptap/extension-text-style';
import Bold from '@tiptap/extension-bold';
import Italic from '@tiptap/extension-italic';
import Underline from '@tiptap/extension-underline';
import Strike from '@tiptap/extension-strike';
import Superscript from '@tiptap/extension-superscript';
import Subscript from '@tiptap/extension-subscript';
import BulletList from '@tiptap/extension-bullet-list'
import OrderedList from '@tiptap/extension-ordered-list'
import ListItem from '@tiptap/extension-list-item'
import Link from '@tiptap/extension-link'
import Table from '@tiptap/extension-table'
import TableCell from '@tiptap/extension-table-cell'
import TableHeader from '@tiptap/extension-table-header'
import TableRow from '@tiptap/extension-table-row'
import Text from '@tiptap/extension-text'


const { Application } = Shopware;

class HtmlToJsonService {
    public transform(content: string): string {
        return JSON.stringify(generateJSON(content, [
            Document,
            Heading,
            Paragraph,
            TextStyle,
            Bold,
            Italic,
            Underline,
            Strike,
            Superscript,
            Subscript,
            BulletList,
            OrderedList,
            ListItem,
            Link,
            Table,
            TableCell,
            TableHeader,
            TableRow,
            Text,
        ]));
    }
}

Application.addServiceProvider('htmlToJsonService', () => new HtmlToJsonService());

/**
 * @private
 * @sw-package discovery
 */
export default HtmlToJsonService;
