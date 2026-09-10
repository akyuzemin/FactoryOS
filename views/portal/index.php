<?php

$pageTitle = 'Dijital Yönetim Merkezi';

require __DIR__ . '/../layouts/header.php';
require __DIR__ . '/../layouts/sidebar.php';
?>

<main class="main-content">

    <header class="page-header">
        <div>
            <p class="page-kicker">Kurumsal Sistem Portalı</p>
            <h1>Dijital Yönetim Merkezi</h1>
            <p class="page-description">Yetkinize tanımlı kurumsal operasyon modüllerine tek ekrandan erişin.</p>
        </div>
        <div class="header-badges">
            <span class="live-badge"><span class="pulse-dot"></span> Sistem Aktif</span>
            <span class="page-count"><?= htmlspecialchars(date('d.m.Y - H:i')) ?></span>
        </div>
    </header>

    <!-- Kullanıcı Karşılama Banner -->
    <div class="portal-welcome-banner">
        <div class="portal-welcome-content">
            <h2>Hoş Geldiniz, <?= htmlspecialchars($_SESSION['username'] ?? 'Kullanıcı') ?></h2>
            <p>Schmid Pekintaş Dijital Fabrika Yönetim Platformu üzerinden yetkili olduğunuz modülü seçerek operasyonlarınıza devam edebilirsiniz.</p>
        </div>
        <div class="portal-user-tag">
            <span class="role-badge"><?php
                $roleNames = [1 => 'Admin', 2 => 'Depo Sorumlusu', 3 => 'Yönetici', 4 => 'Operatör'];
                echo htmlspecialchars($roleNames[$roleId] ?? 'Kullanıcı');
            ?></span>
        </div>
    </div>

    <!-- Modül Seçim Kartları Grid -->
    <div class="portal-grid">

        <!-- Modül 1: Stok Yönetimi -->
        <div class="portal-card portal-card-stock <?= $hasStockView ? '' : 'portal-card-locked' ?>">
            <div class="portal-card-header">
                <div class="portal-card-icon portal-icon-stock">
                    📦
                </div>
                <div class="portal-card-badge-wrap">
                    <?php if ($hasStockView): ?>
                        <span class="status-badge status-badge-success">Aktif Modül</span>
                    <?php else: ?>
                        <span class="status-badge status-badge-danger">Yetki Gerekli</span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="portal-card-body">
                <h3>Stok &amp; Envanter Yönetimi</h3>
                <p class="portal-card-desc">
                    Hammadde, yarı mamul, sarf malzeme stokları, depo ve raf lokasyonları, 6 tip stok hareketi, kritik seviye uyarıları ve dinamik raporlama.
                </p>

                <ul class="portal-card-features">
                    <li><span class="feature-check">✓</span> Malzeme &amp; Kategori Tanımları</li>
                    <li><span class="feature-check">✓</span> Depo &amp; Raf Lokasyon Hiyerarşisi</li>
                    <li><span class="feature-check">✓</span> Giriş, Çıkış, Transfer &amp; Düzeltme İşlemleri</li>
                    <li><span class="feature-check">✓</span> Analitik Raporlar &amp; CSV Dışa Aktarım</li>
                </ul>
            </div>

            <div class="portal-card-footer">
                <?php if ($hasStockView): ?>
                    <a href="/stok-takip/public/" class="button button-primary portal-btn">
                        Stok Sistemine Git &rarr;
                    </a>
                <?php else: ?>
                    <button class="button portal-btn is-disabled" disabled>
                        Erişim Yetkiniz Bulunmuyor
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Modül 2: Enerji Yönetimi (Aktif Modül) -->
        <div class="portal-card portal-card-energy">
            <div class="portal-card-header">
                <div class="portal-card-icon portal-icon-energy">
                    ⚡
                </div>
                <div class="portal-card-badge-wrap">
                    <span class="status-badge status-badge-success">Aktif Modül</span>
                </div>
            </div>

            <div class="portal-card-body">
                <h3>Enerji &amp; Tesis Yönetimi</h3>
                <p class="portal-card-desc">
                    Fabrika enerji tüketim izleme, 1.2 MWp Çatı GES solar üretim analizi, laminatör ve hat spesifik tüketimi (kWh/panel), puant yük analitiği ve kural tabanlı tasarruf fırsatları.
                </p>

                <ul class="portal-card-features">
                    <li><span class="feature-check">✓</span> Canlı Şebeke Yükü &amp; 1.2 MWp Çatı GES Takibi</li>
                    <li><span class="feature-check">✓</span> Laminatör &amp; Hat Spesifik Enerji (SEC)</li>
                    <li><span class="feature-check">✓</span> 3 Zamanlı (T1, T2, T3) Tarife &amp; Maliyet Dağılımı</li>
                    <li><span class="feature-check">✓</span> Kural Tabanlı Aylık ~₺173K Tasarruf Analitiği</li>
                </ul>
            </div>

            <div class="portal-card-footer">
                <a href="/stok-takip/public/energy-dashboard" class="button button-primary portal-btn portal-btn-energy">
                    Enerji Sistemine Git &rarr;
                </a>
            </div>
        </div>

    </div>

</main>
