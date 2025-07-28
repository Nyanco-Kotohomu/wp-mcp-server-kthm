# MCP Server for WordPress

AIエージェントと連携するためのMCPサーバー機能をWordPressに追加するプラグインです。

このプラグインは、来るべきAIエージェント時代において、WebサイトがAIにとって単なる「情報源」ではなく、具体的なタスクを実行できる「ツール」となることを目指して開発された、**実験的なプロジェクト**です。

## ご注意 (Disclaimer)

このプラグインは現在、実験的なプロジェクトとして開発・公開されています。機能は将来的に変更される可能性があり、動作を保証するものではありません。本番環境でのご利用は、リスクを理解した上で自己責任にてお願いいたします。

## 機能 (Features)

本プラグインは、AIエージェントがサイトの機能を自律的に利用できるよう、MCP (Model Context Protocol) に準拠した以下のAPIエンドポイントを提供します。

* **問い合わせ機能**:
    * 連携するNinja Formsの仕様（スキーマ）をAIに提供します。
    * AIからのリクエストを受け付け、Ninja Formsに安全に問い合わせ内容を登録します。
* **レポート（ファイル）取得機能**:
    * 管理者が指定したカテゴリやタグに属する記事の中から、ファイルが添付された記事の一覧をAIに提供します。
    * 特定の記事に含まれるファイルのURLやファイル名などの詳細情報をAIに提供します。

## 動作環境 (Requirements)

* WordPress 6.0 以上
* PHP 8.2 以上
* Ninja Forms プラグイン

## インストール (Installation)

1.  [このリポジトリ](https://github.com/Nyanco-Kotohomu/wp-mcp-server-kthm)から最新版のzipファイルをダウンロードします。
2.  WordPressの管理画面から \[プラグイン] > \[新規追加] > \[プラグインのアップロード] を選択し、ダウンロードしたzipファイルをアップロードします。
3.  プラグインを有効化します。

## 設定方法 (Configuration)

1.  WordPressの管理画面で **\[設定] > \[MCP Server]** を開きます。
2.  **認証設定**:
    * **\[トークンを生成]** ボタンをクリックして、安全な秘密のトークンを生成します。このトークンは、AIがAPIを利用する際の認証に使います。
3.  **Ninja Forms 連携設定**:
    * **\[連携フォーム]** のプルダウンから、AIからの問い合わせを受け付けたいNinja Formsのフォームを選択します。
    * （任意）AIが送ってくるJSONのキー名とフォームのキー名が異なる場合は、**\[フィールドマッピング]** で翻訳ルールを設定します。
4.  **レポート一覧設定**:
    * **\[対象タクソノミー]** と **\[対象ターム]** を選択し、AIに公開したいレポート記事が含まれるカテゴリやタグを指定します。
5.  **\[変更を保存]** ボタンをクリックします。

## AIエージェントとの連携方法

このプラグインのAPIをAIエージェント（ChatGPTのGPTsやGoogleのGemsなど）に「ツール」として使わせるには、AIのカスタム設定画面で**OpenAPIスキーマ**を登録する必要があります。

### Gemini (Gems) との連携

1.  Google AI Studioなどで新しいGemを作成します。
2.  「ツール」の設定で、APIの種類として「OpenAPI」を選択します。
3.  後述のOpenAPIスキーマを貼り付け、`servers`の`url`をあなたのサイトのURLに書き換えます。
4.  認証設定では、このプラグインで生成した「秘密のトークン」を、クライアント側スクリプトなどで署名計算に利用するよう設定します。

### ChatGPT (GPTs) との連携

1.  ChatGPTのサイトでGPTエディターを開き、新しいGPTを作成します。
2.  「Configure」タブの下部にある「Actions」セクションを開きます。
3.  後述のOpenAPIスキーマを「Schema」の欄に貼り付け、`servers`の`url`をあなたのサイトのURLに書き換えます。
4.  認証設定（Authentication）では、`Authentication Type`として`API Key`を選択し、`Auth Type`を`Bearer`に設定します。`API Key`の欄には、このプラグインで生成した「秘密のトークン」を貼り付けます。（**注**: GPTsのBearer認証は、私たちのリクエスト署名とは直接互換性がないため、GPTsがAPIを呼び出すためのプロキシや中間サーバーが必要になる場合があります。）

### Google Workspace Gemini との連携 (上級者向け)

Google Workspace環境でGeminiにこのプラグインを使わせる場合、組織のセキュリティポリシーにより、外部APIへの直接接続が制限されていることがあります。その場合、**Google Apps Script**を中間サーバー（プロキシ）として利用するのが、最も安全で推奨される方法です。

1.  **Google Apps Scriptの準備**:
    * 新しいApps Scriptプロジェクトを作成します。
    * Apps Script内に、Geminiから呼び出される関数（例: `inquireToKotohomu`）を作成します。
    * その関数の中で、`UrlFetchApp`サービスを使い、私たちのプラグインのAPI (`/inquiry`) を呼び出す処理を記述します。タイムスタンプの生成や署名の計算も、すべてこのApps Script内で行います。
2.  **Gemini (Workspace) の設定**:
    * Geminiのツール設定で、「Apps Script」をツールとして選択します。
    * 上記で作成したApps Scriptのプロジェクトと関数を、Geminiが利用できるツールとして登録します。

この方法により、認証情報（秘密のトークン）をGoogleのインフラ内で安全に管理しつつ、Geminiに外部の機能を実行させることが可能になります。

---
### OpenAPI スキーマ (共通)

```yaml
openapi: 3.1.0
info:
  title: MCP Server for WordPress API
  description: WordPressサイトの問い合わせやレポート取得機能と連携するためのAPI。
  version: 1.0.0
servers:
  - url: [https://あなたのサイト.com/wp-json](https://あなたのサイト.com/wp-json)
paths:
  /mcp/v1/inquiry/schema:
    post:
      summary: 問い合わせフォームの仕様を取得
      description: 連携中のNinja Formsのフィールド情報を取得します。
      security:
        - McpAuth: []
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties:
                form_id:
                  type: integer
      responses:
        '200':
          description: 成功。フォームのスキーマを返します。
  /mcp/v1/inquiry:
    post:
      summary: 問い合わせを実行
      description: 取得したスキーマに基づいて、問い合わせデータを送信します。
      security:
        - McpAuth: []
      requestBody:
        content:
          application/json:
            schema:
              type: object
              properties: {}
      responses:
        '200':
          description: 成功。
  /mcp/v1/reports:
    get:
      summary: レポート記事の一覧を取得
      description: 設定されたカテゴリに属する、ファイル付きの記事一覧を返します。
      security:
        - McpAuth: []
      responses:
        '200':
          description: 成功。レポート記事のリストを返します。
  /mcp/v1/reports/{id}:
    get:
      summary: 個別のレポートファイルを取得
      description: 指定された記事IDに含まれるファイル情報を取得します。
      security:
        - McpAuth: []
      parameters:
        - name: id
          in: path
          required: true
          schema:
            type: integer
      responses:
        '200':
          description: 成功。ファイル情報のリストを返します。
components:
  securitySchemes:
    McpAuth:
      type: apiKey
      in: header
      name: x-mcp-signature # 実際にはタイムスタンプとの組み合わせで計算
```

## ライセンス (License)

このプラグインは [GPL-3.0 license](https://www.gnu.org/licenses/gpl-3.0.html) の下で公開されています。

## 開発者 (Author)

* [ことほむ LLC](https://kotohomu.com/)
