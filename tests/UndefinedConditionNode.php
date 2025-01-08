<?php

declare(strict_types=1);

namespace Dbp\Relay\CoreConnectorLdapBundle\Tests;

use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\ConditionNode;
use Dbp\Relay\CoreBundle\Rest\Query\Filter\Nodes\OperatorType;

class UndefinedConditionNode extends ConditionNode
{
    public function __construct()
    {
        parent::__construct('foo', OperatorType::EQUALS_OPERATOR, 'bar');
    }

    public function getOperator(): string
    {
        return 'undefined';
    }
}
