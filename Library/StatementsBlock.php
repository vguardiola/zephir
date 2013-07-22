<?php

/**
 * StatementsBlock
 *
 * This represent a list of statements like the one in a method
 * or a if/else/while
 */
class StatementsBlock
{
	protected $_statements;

	protected $_lastStatement;

	public function __construct(array $statements)
	{
		$this->_statements = $statements;
	}

	public function compile(CompilationContext $compilationContext)
	{

		$compilationContext->codePrinter->increaseLevel();

		foreach ($this->_statements as $statement) {

			switch ($statement['type']) {
				case 'let':
					$letStatement = new LetStatement($statement);
					$letStatement->compile($compilationContext);
					break;
				case 'echo':
					$echoStatement = new EchoStatement($statement);
					$echoStatement->compile($compilationContext);
					break;
				case 'declare':
					$declareStatement = new DeclareStatement($statement);
					$declareStatement->compile($compilationContext);
					break;
				case 'if':
					$ifStatement = new IfStatement($statement);
					$ifStatement->compile($compilationContext);
					break;
				case 'while':
					$whileStatement = new WhileStatement($statement);
					$whileStatement->compile($compilationContext);
					break;
				case 'return':
					$returnStatement = new ReturnStatement($statement);
					$returnStatement->compile($compilationContext);
					break;
				case 'loop':
					$loopStatement = new LoopStatement($statement);
					$loopStatement->compile($compilationContext);
					break;
				case 'break':
					$breakStatement = new BreakStatement($statement);
					$breakStatement->compile($compilationContext);
					break;
				case 'continue':
					$continueStatement = new ContinueStatement($statement);
					$continueStatement->compile($compilationContext);
					break;
			}

			if ($statement['type'] != 'comment') {
				$this->_lastStatement = $statement;
			}
		}

		$compilationContext->codePrinter->decreaseLevel();
	}

	/**
	 *
	 */
	public function getLastStatementType()
	{
		return $this->_lastStatement['type'];
	}

}
