interface LANG {
    [key: string]: string; // 或者 any，表示 LANG 可以有任意 key
}

declare const LANG: LANG;
declare function moment(inp?: any): any;