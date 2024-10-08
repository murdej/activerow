<?php

/**
 * This file is part of the Nette Framework (https://nette.org)
 * Copyright (c) 2004 David Grudl (https://davidgrudl.com)
 */

namespace Murdej\ActiveRow\NReflection;


/**
 * Code annotation.
 */
interface IAnnotation
{
	function __construct(array $values);
}
