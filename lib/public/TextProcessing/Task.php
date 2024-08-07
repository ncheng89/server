<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCP\TextProcessing;

/**
 * This is a text processing task
 * @since 27.1.0
 * @psalm-template-covariant T of ITaskType
 * @deprecated 30.0.0
 */
final class Task implements \JsonSerializable {
	protected ?int $id = null;
	protected ?string $output = null;
	private ?\DateTime $completionExpectedAt = null;

	/**
	 * @since 27.1.0
	 */
	public const TYPES = [
		FreePromptTaskType::class,
		SummaryTaskType::class,
		HeadlineTaskType::class,
		TopicsTaskType::class,
	];

	/**
	 * @since 27.1.0
	 */
	public const STATUS_FAILED = 4;
	/**
	 * @since 27.1.0
	 */
	public const STATUS_SUCCESSFUL = 3;
	/**
	 * @since 27.1.0
	 */
	public const STATUS_RUNNING = 2;
	/**
	 * @since 27.1.0
	 */
	public const STATUS_SCHEDULED = 1;
	/**
	 * @since 27.1.0
	 */
	public const STATUS_UNKNOWN = 0;

	/**
	 * @psalm-var self::STATUS_*
	 */
	protected int $status = self::STATUS_UNKNOWN;

	/**
	 * @psalm-param class-string<T> $type
	 * @param string $type
	 * @param string $input
	 * @param string $appId
	 * @param string|null $userId
	 * @param string $identifier An arbitrary identifier for this task. max length: 255 chars
	 * @since 27.1.0
	 */
	final public function __construct(
		protected string $type,
		protected string $input,
		protected string $appId,
		protected ?string $userId,
		protected string $identifier = '',
	) {
	}

	/**
	 * @psalm-param IProvider<T> $provider
	 * @param IProvider $provider
	 * @return string
	 * @since 27.1.0
	 */
	public function visitProvider(IProvider $provider): string {
		if ($this->canUseProvider($provider)) {
			if ($provider instanceof IProviderWithUserId) {
				$provider->setUserId($this->getUserId());
			}
			return $provider->process($this->getInput());
		} else {
			throw new \RuntimeException('Task of type ' . $this->getType() . ' cannot visit provider with task type ' . $provider->getTaskType());
		}
	}

	/**
	 * @psalm-param IProvider<T> $provider
	 * @param IProvider $provider
	 * @return bool
	 * @since 27.1.0
	 */
	public function canUseProvider(IProvider $provider): bool {
		return $provider->getTaskType() === $this->getType();
	}

	/**
	 * @psalm-return class-string<T>
	 * @since 27.1.0
	 */
	final public function getType(): string {
		return $this->type;
	}

	/**
	 * @return string|null
	 * @since 27.1.0
	 */
	final public function getOutput(): ?string {
		return $this->output;
	}

	/**
	 * @param string|null $output
	 * @since 27.1.0
	 */
	final public function setOutput(?string $output): void {
		$this->output = $output;
	}

	/**
	 * @psalm-return self::STATUS_*
	 * @since 27.1.0
	 */
	final public function getStatus(): int {
		return $this->status;
	}

	/**
	 * @psalm-param self::STATUS_* $status
	 * @since 27.1.0
	 */
	final public function setStatus(int $status): void {
		$this->status = $status;
	}

	/**
	 * @return int|null
	 * @since 27.1.0
	 */
	final public function getId(): ?int {
		return $this->id;
	}

	/**
	 * @param int|null $id
	 * @since 27.1.0
	 */
	final public function setId(?int $id): void {
		$this->id = $id;
	}

	/**
	 * @return string
	 * @since 27.1.0
	 */
	final public function getInput(): string {
		return $this->input;
	}

	/**
	 * @return string
	 * @since 27.1.0
	 */
	final public function getAppId(): string {
		return $this->appId;
	}

	/**
	 * @return string
	 * @since 27.1.0
	 */
	final public function getIdentifier(): string {
		return $this->identifier;
	}

	/**
	 * @return string|null
	 * @since 27.1.0
	 */
	final public function getUserId(): ?string {
		return $this->userId;
	}

	/**
	 * @psalm-return array{id: ?int, type: class-string<T>, status: 0|1|2|3|4, userId: ?string, appId: string, input: string, output: ?string, identifier: string, completionExpectedAt: ?int}
	 * @since 27.1.0
	 */
	public function jsonSerialize(): array {
		return [
			'id' => $this->getId(),
			'type' => $this->getType(),
			'status' => $this->getStatus(),
			'userId' => $this->getUserId(),
			'appId' => $this->getAppId(),
			'input' => $this->getInput(),
			'output' => $this->getOutput(),
			'identifier' => $this->getIdentifier(),
			'completionExpectedAt' => $this->getCompletionExpectedAt()?->getTimestamp(),
		];
	}

	/**
	 * @param null|\DateTime $completionExpectedAt
	 * @return void
	 * @since 28.0.0
	 */
	final public function setCompletionExpectedAt(?\DateTime $completionExpectedAt): void {
		$this->completionExpectedAt = $completionExpectedAt;
	}

	/**
	 * @return \DateTime|null
	 * @since 28.0.0
	 */
	final public function getCompletionExpectedAt(): ?\DateTime {
		return $this->completionExpectedAt;
	}
}
