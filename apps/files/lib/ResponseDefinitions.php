<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files;

/**
 * @psalm-type FilesTemplateFile = array{
 *     basename: string,
 *     etag: string,
 *     fileid: int,
 *     filename: ?string,
 *     lastmod: int,
 *     mime: string,
 *     size: int,
 *     type: string,
 *     hasPreview: bool,
 * }
 *
 * @psalm-type FilesTemplateFileCreator = array{
 *     app: string,
 *     label: string,
 *     extension: string,
 *     iconClass: ?string,
 *     iconSvgInline: ?string,
 *     mimetypes: string[],
 *     ratio: ?float,
 *     actionLabel: string,
 * }
 *
 * @psalm-type FilesTemplateField = array{
 *     index: string,
 *     content: string,
 *     type: string,
 * }
 *
 * @psalm-type FilesFolderTreeNode = array{
 *     id: int,
 *     displayName?: string,
 *     children?: array<string, array{}>,
 * }
 *
 * @psalm-type FilesFolderTree = array<string, FilesFolderTreeNode>
 *
 */
class ResponseDefinitions {
}
