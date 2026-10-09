#!/usr/bin/env python3
"""Read-only comparison of the reviewed public documentation sources."""
import concurrent.futures
import hashlib
import json
from html.parser import HTMLParser
from pathlib import Path
import urllib.parse
import urllib.request


class Article(HTMLParser):
    def __init__(self):
        super().__init__()
        self.active = False
        self.parts = []

    def handle_starttag(self, tag, attrs):
        if tag == 'article':
            self.active = True

    def handle_endtag(self, tag):
        if tag == 'article':
            self.active = False

    def handle_data(self, data):
        if self.active and data.strip():
            self.parts.append(data.strip())


def allowed(url):
    parsed = urllib.parse.urlsplit(url)
    return (parsed.scheme == 'https' and parsed.netloc == 'docs.zhifux.com'
            and parsed.path.startswith('/read/zhifufm/'))


class SameOrigin(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, fp, code, msg, headers, newurl):
        if not allowed(newurl):
            raise ValueError('Redirect outside the reviewed documentation origin')
        return super().redirect_request(request, fp, code, msg, headers, newurl)


def check(page):
    try:
        if not allowed(page['url']):
            raise ValueError('Unexpected source URL')
        request = urllib.request.Request(page['url'], headers={'User-Agent': 'Epay-help-source-review/1.0'})
        with urllib.request.build_opener(SameOrigin()).open(request, timeout=30) as response:
            raw = response.read(2_000_001)
        if len(raw) > 2_000_000:
            raise ValueError('Source page exceeds review size limit')
        article = Article()
        article.feed(raw.decode('utf-8'))
        if not article.parts:
            raise ValueError('Article body not found')
        digest = hashlib.sha256('\n'.join(article.parts).encode()).hexdigest()
        return {'slug': page['slug'], 'result': 'unchanged' if digest == page['sha256'] else 'changed'}
    except Exception as error:
        return {'slug': page['slug'], 'result': 'failed', 'reason': str(error)}


if __name__ == '__main__':
    source = Path(__file__).resolve().parents[1] / 'docs/help/sources.json'
    pages = json.loads(source.read_text())['pages']
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(check, pages))
    for result in results:
        if result['result'] != 'unchanged':
            print(json.dumps(result, ensure_ascii=False))
    counts = {kind: sum(r['result'] == kind for r in results) for kind in ['unchanged', 'changed', 'failed']}
    print(json.dumps(counts, ensure_ascii=False))
    raise SystemExit(1 if counts['failed'] or counts['changed'] else 0)
