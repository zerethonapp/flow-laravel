# 📁 archon-laravel — Recommended Repo Structure

## 🎯 Purpose

`archon-laravel` is the first real instrumentation adapter for ArchonFlow.

Its job is to:
- hook into Laravel runtime execution
- capture real request flow
- convert Laravel execution into ArchonFlow trace format
- feed real traces into `archon-engine` and `archon-cli`

This package should remain focused on **instrumentation only**.

It should **not** own:
- bottleneck scoring
- confidence logic
- insight generation
- CLI formatting

---

## 🧱 Recommended Repository Structure

```text
archon-laravel/
├── src/
│   ├── Providers/
│   │   └── ArchonFlowServiceProvider.php
│   ├── Facades/
│   │   └── Archon.php
│   ├── Contracts/
│   │   ├── TraceCollectorInterface.php
│   │   ├── InstrumentationManagerInterface.php
│   │   └── TraceWriterInterface.php
│   ├── Instrumentation/
│   │   ├── InstrumentationManager.php
│   │   ├── TraceCollector.php
│   │   ├── TraceContext.php
│   │   ├── NodeBuilder.php
│   │   ├── EdgeBuilder.php
│   │   ├── TraceWriter.php
│   │   └── Hooks/
│   │       ├── RequestHook.php
│   │       ├── ControllerHook.php
│   │       ├── DatabaseHook.php
│   │       └── ExternalCallHook.php
│   ├── Middleware/
│   │   └── CaptureArchonTrace.php
│   ├── Support/
│   │   ├── GeneratesTraceIds.php
│   │   ├── Clock.php
│   │   └── ArrangesParentChildFlow.php
│   ├── Helpers/
│   │   └── archon.php
│   └── Commands/
│       └── InstallArchonFlowCommand.php
├── config/
│   └── archonflow.php
├── tests/
│   ├── Feature/
│   │   └── CaptureTraceTest.php
│   └── Unit/
│       ├── TraceCollectorTest.php
│       └── InstrumentationManagerTest.php
├── composer.json
├── README.md
└── .gitignore