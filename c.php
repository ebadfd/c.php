<?php 


function todo($message){
    throw new ErrorException("ERROR: not implemented " . $message);
}

enum TokenEnum : string
{
    case TokenName = 'TOKEN_NAME';
    case TokenOParan = 'TOKEN_OPARAN';
    case TokenCParan = 'TOKEN_CPARAN';
    case TokenOCurly = 'TOKEN_OCURLY';
    case TokenCCurly= 'TOKEN_CCURLY';
    case TokenSemiColon = 'TOKEN_SEMICOLON';
    case TokenNumber= 'TOKEN_NUMBER';
    case TokenString = 'TOKEN_STRING';
    case TokenReturn = 'TOKEN_RETURN';
}

class Token {
    public TokenEnum $type;
    public string $value;
    public Loc $loc;

    public function __construct(Loc $loc,TokenEnum $type,string $value) {
        $this->loc = $loc;
        $this->type = $type;
        $this->value = $value;
    }
}


class Loc {
    public string $file_path;
    public int $row;
    public int $col;

    public function __construct(string $file_path,int  $row, int $col) {
        $this->file_path = $file_path;
        $this->row = $row;
        $this->col = $col;
    }

    public function display(): string {
        return sprintf("%s:%d:%d", $this->file_path, $this->row + 1, $this->col + 1);
    }
}


class Lexer {
    public string $source;
    public int $cur;
    public int $bol;
    public int $row;
    public string $file_path;
    public $literal_tokens = array( 
        "(" => TokenEnum::TokenOParan,
        ")" => TokenEnum::TokenCParan,
        "{" => TokenEnum::TokenOCurly,
        "}" => TokenEnum::TokenCCurly,
        ";" => TokenEnum::TokenSemiColon,
    );

    function __construct(string $source, string $file_path){
        $this->source = $source;
        $this->file_path = $file_path;
        $this->cur = 0;
        $this->bol = 0;
        $this->row = 0;
    }

    private function is_eof(): bool {
        return !$this->has_char();
    }

    private function has_char(): bool {
        return $this->cur < strlen($this->source ?? '');
    }

    private function drop_line(): void {
        while ($this->has_char() && $this->source[$this->cur] !== "\n") {
            $this->chop_char();
        }
        // stops at new line and drop the new line char as well
        if ($this->has_char()) {
            $this->chop_char();
        }
    }

    private function trim_left(): void {
        while ($this->has_char() && ctype_space($this->source[$this->cur])) {
            $this->chop_char();
        }
    }

    private function chop_char():void {
        if($this->has_char()) {
            $x = $this->source[$this->cur];
            $this->cur += 1;

            if($x === "\n"){
                $this->bol = $this->cur;
                $this->row +=1;
            }
        }
    }

    private function loc(): Loc {
        return new Loc(file_path: $this->file_path, row: $this->row, col: $this->cur - $this->bol);
    }

    public function next_token(): Token | bool {
        $this->trim_left();

        if(str_starts_with(substr($this->source, $this->cur), "#")) {
            $this->drop_line();
            $this->trim_left();
        }

        if($this->is_eof()) {
            return false;
        }

        $loc = $this->loc();
        $first = $this->source[$this->cur];

        // int main() {
        // ^    ^
        // int
        //     main

        if(ctype_alpha(text: $first)){
            $index = $this->cur;

            while($this->has_char() && ctype_alnum($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = substr($this->source, $index, $this->cur - $index);
            return new Token(loc: $loc, type: TokenEnum::TokenName, value: $value );
        }


        if(isset($this->literal_tokens[$first])){
            $this->chop_char();
            $t = $this->literal_tokens[$first];
            return new Token(loc: $loc, type: $t, value: $first);
        }

        if($first === '"') {
            $this->chop_char();
            $start = $this->cur;

            while($this->has_char() && $this->source[$this->cur] !== '"') {
                $this->chop_char();
            }

            if($this->has_char()) {
                $value = substr($this->source, $start, $this->cur - $start);
                $this->chop_char();
                return new Token(loc: $loc, type: TokenEnum::TokenString, value: $value);
            }

            echo sprintf("%s: ERROR: unclosed string literal\n", $loc->display());
            return false;
        }

        if(ctype_digit($first)) {
            $start = $this->cur;

            while($this->has_char() && ctype_digit($this->source[$this->cur])) {
                $this->chop_char();
            }

            $value = (int) substr($this->source, $start, $this->cur - $start);
            return new Token(loc: $loc, type: TokenEnum::TokenNumber, value: $value);
        }

        todo("next_token()");
    }
}


$source = file_get_contents("./main.c") or die;
$lexer = new Lexer(source: $source, file_path: "main.c");

while (true) {
    $token = $lexer->next_token();

    if(!$token) {
        break;
    }

    echo sprintf("%s: %s | %s\n", $token->loc->display(), $token->type->value, $token->value);
}
